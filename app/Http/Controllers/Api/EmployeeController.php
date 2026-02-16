<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Task;
use App\Models\Presence;
use App\Models\LeaveRequest;
use App\Models\Announcement;
use App\Models\User;
use App\Mail\UserWelcomeEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class EmployeeController extends Controller
{

    public function getDashboardSummary()
    {
        try {
            $user = Auth::user();
            // Utiliser une jointure ou charger uniquement l'essentiel
            $employeeId = $user->employee->id;

            if (!$employeeId) {
                return response()->json(["message" => "Profil non trouvé"], 404);
            }

            // On récupère tout en limitant les colonnes (Select) pour réduire le poids du JSON
            return response()->json([
                'presences' => Presence::where('employee_id', $employeeId)
                    ->select('id', 'date', 'status')
                    ->latest()
                    ->take(5)
                    ->get(),
                'tasks' => Task::where('employee_id', $employeeId)
                    ->select('id', 'title', 'due_date', 'status')
                    ->latest()
                    ->take(5)
                    ->get(),
                'leave_requests' => LeaveRequest::where('employee_id', $employeeId)
                    ->select('id', 'start_date', 'end_date', 'status')
                    ->latest()
                    ->take(5)
                    ->get(),
                'announcements' => Announcement::where('is_general', true)
                    ->orWhere('department_id', $user->employee->department_id)
                    ->select('id', 'title', 'created_at', 'is_general')
                    ->latest()
                    ->take(5)
                    ->get()
            ]);
        } catch (Throwable $e) {
            return response()->json(["message" => "Erreur", "error" => $e->getMessage()], 500);
        }
    }

    public function me()
    {
        try {
            $employee = Auth::user()->employee;

            if (!$employee) {
                return response()->json(["message" => "Aucun profil employé associé."], 404);
            }

            $employee->load(['department', 'roles']);

            return response()->json([
                "message" => "Profil de l'employé connecté",
                "employee" => $employee
            ]);
        } catch (Throwable $e) {
            Log::error("Erreur dans me(): " . $e->getMessage());
            return response()->json(["message" => "Erreur interne"], 500);
        }
    }

    /**
     * NOUVELLE MÉTHODE : Vérifier si l'utilisateur est manager
     */
    public function checkManagerStatus()
    {
        try {
            $user = Auth::user();
            $employee = $user->employee;

            // Normaliser le rôle
            $userRole = strtolower($user->role);

            // 1. Est-ce qu'il a le rôle admin ?
            $isAdmin = $userRole === 'admin';

            // 2. Est-ce qu'il est marqué comme manager
            $isManagerRole = $userRole === 'manager';

            $isEmployeeManager = false;
            $departmentId = null;
            $departmentName = null;

            if ($employee) {
                $departmentId = $employee->department_id;
                $departmentName = $employee->department->name ?? null;

                // Vérification 1: Table pivot roles
                $hasManagerRole = $employee->roles()
                    ->whereRaw('LOWER(name) = ?', ['manager'])
                    ->exists();

                // Vérification 2: Table managers
                $existsInManagersTable = \App\Models\Manager::where('employee_id', $employee->id)->exists();

                $isEmployeeManager = $hasManagerRole || $existsInManagersTable;
            }

            $isManager = $isAdmin || $isManagerRole || $isEmployeeManager;

            return response()->json([
                'is_manager' => (bool)$isManager,
                'is_admin' => (bool)$isAdmin,
                'department_id' => $departmentId,
                'department_name' => $departmentName,
                'debug' => [
                    'user_role' => $user->role,
                    'in_managers_table' => $existsInManagersTable ?? false,
                    'has_pivot_role' => $hasManagerRole ?? false
                ]
            ]);
        } catch (Throwable $e) {
            return response()->json(['is_manager' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Upload photo de profil
     */
    /**
     * Upload photo de profil - VERSION CORRIGÉE
     * Route: POST /api/me/profile-photo
     */
    public function uploadPhoto(Request $request)
    {
        Log::info(" === DÉBUT UPLOAD PHOTO ===");
        Log::info("Données reçues:", [
            'all_keys' => array_keys($request->all()),
            'has_profile_photo' => $request->hasFile('profile_photo'),
            'has_photo' => $request->hasFile('photo'),
            'all_files' => array_keys($request->allFiles())
        ]);

        try {
            $employee = Auth::user()->employee;

            if (!$employee) {
                Log::warning("Aucun profil employé");
                return response()->json(["message" => "Profil employé introuvable."], 404);
            }

            Log::info("Employé:", [
                'id' => $employee->id,
                'nom' => $employee->first_name . ' ' . $employee->last_name
            ]);

            // VALIDATION : Accepter 'profile_photo' (pas 'photo')
            $request->validate([
                'profile_photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
            ]);

            Log::info("Validation OK");

            // Supprimer l'ancienne photo
            if ($employee->profile_photo) {
                Log::info("Suppression ancienne photo:", ['path' => $employee->profile_photo]);
                Storage::disk('public')->delete($employee->profile_photo);
            }

            // Sauvegarder la nouvelle photo
            $file = $request->file('profile_photo');
            Log::info("Fichier:", [
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize() . ' bytes',
                'mime' => $file->getMimeType()
            ]);

            $path = $file->store('profile_photos', 'public');
            Log::info("Sauvegarde:", ['path' => $path]);

            // Mettre à jour la BDD
            $employee->profile_photo = $path;
            $employee->save();

            Log::info("BDD mise à jour");

            // L'URL est générée automatiquement par l'accessor
            $url = $employee->profile_photo_url;

            Log::info(" === UPLOAD RÉUSSI ===", ['url' => $url]);

            return response()->json([
                "message" => "Photo de profil mise à jour avec succès",
                "url" => $url,
                "path" => $path,
                "employee" => $employee->fresh(['department', 'roles'])
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error(" Validation échouée:", $e->errors());
            return response()->json([
                "message" => "Fichier invalide",
                "errors" => $e->errors()
            ], 422);
        } catch (Throwable $e) {
            Log::error("ERREUR CRITIQUE:", [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                "message" => "Erreur lors de l'upload",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer photo de profil
     * Route: DELETE /api/me/profile-photo
     */
    public function deletePhoto()
    {
        try {
            $employee = Auth::user()->employee;

            if (!$employee) {
                return response()->json(["message" => "Profil employé introuvable."], 404);
            }

            if ($employee->profile_photo) {
                Storage::disk('public')->delete($employee->profile_photo);
                $employee->profile_photo = null;
                $employee->save();

                Log::info("Photo supprimée pour employé #" . $employee->id);
            }

            return response()->json([
                "message" => "Photo de profil supprimée",
                "employee" => $employee->fresh(['department', 'roles'])
            ], 200);
        } catch (Throwable $e) {
            Log::error("Erreur suppression photo:", ['error' => $e->getMessage()]);
            return response()->json(["message" => "Erreur lors de la suppression"], 500);
        }
    }

    // --- ADMIN CRUD ---

    /**
     * Liste des employés filtrée selon le rôle (CORRIGÉ)
     */
    public function index(): JsonResponse
    {
        try {
            $user = Auth::user();
            $userRole = strtolower($user->role);

            Log::info('EmployeeController::index - Début', [
                'user_id' => $user->id,
                'user_role' => $user->role,
                'user_role_normalized' => $userRole,
                'has_employee' => $user->employee ? true : false,
                'employee_id' => $user->employee->id ?? null,
                'department_id' => $user->employee->department_id ?? null
            ]);

            $query = Employee::with('department');

            // Vérifier si c'est un manager (plusieurs sources possibles)
            $isManager = false;
            $isAdmin = $userRole === 'admin';

            if (!$isAdmin) {
                // Vérification 1: Rôle direct dans users.role
                if ($userRole === 'manager') {
                    $isManager = true;
                }

                // Vérification 2: Table pivot roles
                if ($user->employee) {
                    $hasManagerRole = $user->employee->roles()
                        ->whereRaw('LOWER(name) = ?', ['manager'])
                        ->exists();

                    // Vérification 3: Table managers
                    $existsInManagersTable = \App\Models\Manager::where('employee_id', $user->employee->id)->exists();

                    if ($hasManagerRole || $existsInManagersTable) {
                        $isManager = true;
                    }
                }
            }

            Log::info('Vérification des rôles', [
                'is_admin' => $isAdmin,
                'is_manager' => $isManager
            ]);

            // Si c'est un manager (et PAS admin), filtrer par département
            if ($isManager && !$isAdmin) {

                if (!$user->employee) {
                    Log::warning('⚠️ Manager sans profil employé', ['user_id' => $user->id]);
                    return response()->json([
                        'data' => [],
                        'message' => 'Erreur : Profil employé introuvable.'
                    ], 200);
                }

                $managerDeptId = $user->employee->department_id;
                $managerEmployeeId = $user->employee->id;

                if (!$managerDeptId) {
                    Log::warning('Manager sans département', [
                        'user_id' => $user->id,
                        'employee_id' => $user->employee->id
                    ]);
                    return response()->json([
                        'data' => [],
                        'message' => 'Erreur : Vous n\'avez pas de département assigné.'
                    ], 200);
                }

                Log::info('Filtrage par département', [
                    'department_id' => $managerDeptId,
                    'excluding_manager_id' => $managerEmployeeId
                ]);

                // Filtrer par département ET exclure le manager lui-même
                $query->where('department_id', $managerDeptId)
                    ->where('id', '!=', $managerEmployeeId);
            }

            $employees = $query->orderBy('last_name')->get();

            Log::info('Employés récupérés', [
                'count' => $employees->count(),
                'filtered_by_department' => $isManager && !$isAdmin
            ]);

            return response()->json(['data' => $employees], 200);
        } catch (Throwable $e) {
            Log::error("Erreur dans EmployeeController::index", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(["message" => "Erreur interne"], 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name'    => 'required|string|max:255',
            'last_name'     => 'required|string|max:255',
            'email'         => 'required|email|unique:employees,email|unique:users,email',
            'phone'         => 'nullable|string',
            'contract_type' => 'required|string',
            'hire_date'     => 'required|date|date_format:Y-m-d',
            'salary_base'   => 'required|numeric',
            'department_id' => 'nullable|exists:departments,id',
            'role_ids'      => 'nullable|array',
            'role_ids.*'    => 'exists:roles,id',
        ]);

        try {
            return DB::transaction(function () use ($validated) {
                $temporaryPassword = Str::random(10);

                $user = User::create([
                    'name'     => $validated['first_name'] . ' ' . $validated['last_name'],
                    'email'    => $validated['email'],
                    'password' => Hash::make($temporaryPassword),
                    'role'     => 'employee',
                    'needs_password_change' => true,
                ]);

                $employee = Employee::create(array_merge($validated, [
                    'user_id' => $user->id
                ]));

                if (!empty($validated['role_ids'])) {
                    $employee->roles()->sync($validated['role_ids']);
                }

                Mail::to($user->email)->send(new UserWelcomeEmail(
                    $user->name,
                    $temporaryPassword,
                    $user->email
                ));

                return response()->json([
                    'message' => 'Employé créé et email d\'invitation envoyé.',
                    'employee' => $employee->load(['department', 'roles'])
                ], 201);
            });
        } catch (Throwable $e) {
            Log::error("Erreur création employé: " . $e->getMessage());
            return response()->json(["message" => "Erreur lors de la création", "error" => $e->getMessage()], 500);
        }
    }

    public function show(Employee $employee)
    {
        try {
            $employee->load(['department', 'roles', 'tasks', 'presences', 'leaveRequests']);
            return response()->json($employee);
        } catch (Throwable $e) {
            return response()->json(["message" => "Erreur interne"], 500);
        }
    }

    public function update(Request $request, Employee $employee)
    {
        try {
            $validated = $request->validate([
                'first_name'    => 'sometimes|required|string|max:255',
                'last_name'     => 'sometimes|required|string|max:255',
                'email'         => [
                    'sometimes',
                    'required',
                    'email',
                    'unique:employees,email,' . $employee->id,
                    'unique:users,email,' . ($employee->user_id ?? 'NULL'),
                ],
                'phone'         => 'nullable|string',
                'contract_type' => 'sometimes|required|string',
                'hire_date'     => 'sometimes|required|date|date_format:Y-m-d',
                'salary_base'   => 'sometimes|required|numeric',
                'department_id' => 'nullable|exists:departments,id',
                'role_ids'      => 'nullable|array',
                'role_ids.*'    => 'exists:roles,id',
                'status' => 'sometimes|required|string|in:actif,demission,renvoyer,retraite',
            ]);

            return DB::transaction(function () use ($validated, $employee) {
                $user = $employee->user;
                if ($user) {
                    if (isset($validated['email'])) $user->email = $validated['email'];
                    if (isset($validated['first_name']) || isset($validated['last_name'])) {
                        $user->name = ($validated['first_name'] ?? $employee->first_name) . ' ' . ($validated['last_name'] ?? $employee->last_name);
                    }
                    if ($user->isDirty()) $user->save();
                }

                $employee->update($validated);
                if (isset($validated['role_ids'])) $employee->roles()->sync($validated['role_ids']);

                return response()->json($employee->load(['department', 'roles']));
            });
        } catch (Throwable $e) {
            return response()->json(["message" => "Erreur interne"], 500);
        }
    }

    public function destroy(Employee $employee)
    {
        try {
            if ($employee->profile_photo) {
                Storage::delete($employee->profile_photo);
            }

            if ($employee->user) {
                $employee->user()->delete();
            } else {
                $employee->delete();
            }
            return response()->json(["message" => "Employé supprimé"], 204);
        } catch (Throwable $e) {
            return response()->json(["message" => "Erreur suppression"], 500);
        }
    }
}

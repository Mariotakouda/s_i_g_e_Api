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
use Illuminate\Support\Str;
use Throwable;

class EmployeeController extends Controller
{
    
    //  PROFIL EMPLOYÉ CONNECTÉ (Identique)
      
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
            Log::error("Erreur dans me(): ".$e->getMessage());
            return response()->json(["message" => "Erreur interne"], 500);
        }
    }

    //  TÂCHES DE L'EMPLOYÉ (Identique)
      
    public function myTasks()
    {
        try {
            $employee = Auth::user()->employee;
            if (!$employee) {
                return response()->json(["message" => "Profil employé introuvable."], 404);
            }

            $tasks = Task::where('employee_id', $employee->id)
                ->orderBy('due_date')
                ->get();

            return response()->json($tasks);
        } catch (Throwable $e) {
            Log::error("Erreur dans myTasks(): ".$e->getMessage());
            return response()->json(["message" => "Erreur interne"], 500);
        }
    }

    //  PRÉSENCES (Identique)
      
   public function myPresences()
{
    try {
        $user = Auth::user();
        Log::info("🔍 myPresences - User:", ['id' => $user->id, 'email' => $user->email]);
        
        $employee = $user->employee;
        
        if (!$employee) {
            Log::warning("⚠️ Aucun profil employé pour user " . $user->id);
            return response()->json(["message" => "Profil employé introuvable."], 404);
        }
        
        Log::info("👤 Employee trouvé:", ['id' => $employee->id, 'name' => $employee->first_name]);

        $presences = Presence::where('employee_id', $employee->id)
            ->latest()
            ->get();
            
        Log::info("📋 Présences trouvées:", ['count' => $presences->count()]);

        return response()->json($presences);
    } catch (Throwable $e) {
        Log::error("❌ Erreur dans myPresences(): " . $e->getMessage());
        Log::error($e->getTraceAsString());
        return response()->json(["message" => "Erreur interne", "error" => $e->getMessage()], 500);
    }
}

    //  DEMANDES DE CONGÉ (Identique)
      
    public function myLeaves()
    {
        try {
            $employee = Auth::user()->employee;
            if (!$employee) {
                return response()->json(["message" => "Profil employé introuvable."], 404);
            }

            $leaves = LeaveRequest::where('employee_id', $employee->id)
                ->latest()
                ->get();

            return response()->json($leaves);
        } catch (Throwable $e) {
            Log::error("Erreur dans myLeaves(): ".$e->getMessage());
            return response()->json(["message" => "Erreur interne"], 500);
        }
    }

    //  ANNONCES VISIBLES PAR L'EMPLOYÉ (Identique)
      
    public function myAnnouncements(): JsonResponse
    {
        try {
            $employee = Auth::user()->employee;
            
            if (!$employee) {
                return response()->json(["message" => "Profil employé introuvable."], 404);
            }

            $announcements = Announcement::where(function ($query) use ($employee) {
                $query->where('is_general', true)
                    ->orWhere(function ($q) {
                        $q->whereNull('employee_id')
                          ->whereNull('department_id');
                    });
                
                if ($employee->department_id) {
                    $query->orWhere('department_id', $employee->department_id);
                }
                
                $query->orWhere('employee_id', $employee->id);
            })
            ->with(['employee:id,first_name,last_name,email', 'department:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();

            return response()->json($announcements);
            
        } catch (Throwable $e) {
            Log::error("Erreur dans myAnnouncements(): " . $e->getMessage());
            return response()->json(["message" => "Erreur interne"], 500);
        }
    }

    public function myDepartments()
    {
        try {
            $employee = Auth::user()->employee;
            if (!$employee) return response()->json(["message" => "Profil employé introuvable."], 404);
            return response()->json($employee->department);
        } catch (Throwable $e) {
            Log::error("Erreur dans myDepartments(): ".$e->getMessage());
            return response()->json(["message" => "Erreur interne"], 500);
        }
    }

    public function myRoles()
    {
        try {
            $employee = Auth::user()->employee;
            if (!$employee) return response()->json(["message" => "Profil employé introuvable."], 404);
            return response()->json($employee->roles);
        } catch (Throwable $e) {
            Log::error("Erreur dans myRoles(): ".$e->getMessage());
            return response()->json(["message" => "Erreur interne"], 500);
        }
    }

    public function myLeaveRequests()
    {
        try {
            $employee = Auth::user()->employee;
            if (!$employee) return response()->json(["message" => "Profil employé introuvable."], 404);
            return response()->json($employee->leaveRequests);
        } catch (Throwable $e) {
            Log::error("Erreur dans myLeaveRequests(): ".$e->getMessage());
            return response()->json(["message" => "Erreur interne"], 500);
        }
    }

    // --- ADMIN CRUD ---

    public function index(): JsonResponse 
    {
        try {
            $employees = Employee::select('id', 'first_name', 'last_name', 'email')
                ->orderBy('last_name')
                ->get();
            return response()->json(['data' => $employees], 200); 
        } catch (Throwable $e) {
            return response()->json(["message" => "Erreur interne"], 500); 
        }
    }

    /**
     * Méthode Store avec correction des 3 arguments
     */
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
                    'name'     => $validated['first_name'].' '.$validated['last_name'],
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

                // CORRECTION ICI : Ajout du 3ème argument ($user->email)
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
            Log::error("Erreur création employé: ".$e->getMessage());
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
                    'sometimes', 'required', 'email',
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
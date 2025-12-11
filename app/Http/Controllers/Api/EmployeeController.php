<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Task;
use App\Models\Presence;
use App\Models\LeaveRequest;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmployeeController extends Controller
{
    
    //  PROFIL EMPLOYÉ CONNECTÉ (OK)
      
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

    
    //  TÂCHES DE L'EMPLOYÉ (OK)
      
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

    
    //  PRÉSENCES (OK)
      
    public function myPresences()
    {
        try {
            $employee = Auth::user()->employee;
            if (!$employee) {
                return response()->json(["message" => "Profil employé introuvable."], 404);
            }

            $presences = Presence::where('employee_id', $employee->id)
                ->latest()
                ->get();

            return response()->json($presences);
        } catch (Throwable $e) {
            Log::error("Erreur dans myPresences(): ".$e->getMessage());
            return response()->json(["message" => "Erreur interne"], 500);
        }
    }

    
    //  DEMANDES DE CONGÉ (OK)
      
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

    
    //  ANNONCES VISIBLES PAR L'EMPLOYÉ (OK)
      
    public function myAnnouncements()
    {
        try {
            $employee = Auth::user()->employee;
            if (!$employee) {
                return response()->json(["message" => "Profil employé introuvable."], 404);
            }

            $announcements = Announcement::where(function ($q) use ($employee) {
                $q->whereNull('employee_id')
                  ->orWhere('employee_id', $employee->id);
            })
            ->latest()
            ->get();

            return response()->json($announcements);
        } catch (Throwable $e) {
            Log::error("Erreur dans myAnnouncements(): ".$e->getMessage());
            return response()->json(["message" => "Erreur interne"], 500);
        }
    }

    
    //  DEPARTEMENT DE L'EMPLOYÉ (OK)
      
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

    
    //  ROLES DE L'EMPLOYÉ (OK)
      
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

    
    //  HISTORIQUE DE DEMANDES DE CONGÉS (OK)
      
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



    // ADMIN CRUD EMPLOYEES

    /**
     * Liste des employés (admin) - OK
     */
    public function index(): JsonResponse 
    {
        try {
            // Renvoie les champs minimum nécessaires pour un listing
            $employees = Employee::select('id', 'first_name', 'last_name', 'email')
                ->orderBy('last_name')
                ->get();

            return response()->json(['data' => $employees], 200); 
        } catch (Throwable $e) {
            Log::error("Erreur dans index(): " . $e->getMessage());

            return response()->json([
                "message" => "Erreur interne lors de la récupération des employés."
            ], 500); 
        }
    }

    /**
     * Création d'un employé (Méthode Admin) - OK
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'first_name'    => 'required|string|max:255',
                'last_name'     => 'required|string|max:255',
                // L'email doit être unique dans les deux tables
                'email'         => 'required|email|unique:employees,email|unique:users,email', 
                'phone'         => 'nullable|string',
                'contract_type' => 'required|string',
                'hire_date'     => 'required|date|date_format:Y-m-d',
                'salary_base'   => 'required|numeric',
                'department_id' => 'nullable|exists:departments,id',
                'role_ids'      => 'nullable|array',
                'role_ids.*'    => 'exists:roles,id',
            ]);

            // ÉTAPE 1 : Création du compte User pour l'authentification
            $initialPassword = 'password1234'; 
            $user = User::create([
                'name'     => $validated['first_name'].' '.$validated['last_name'],
                'email'    => $validated['email'],
                'password' => Hash::make($initialPassword),
                'role'     => 'employee',
            ]);

            // ÉTAPE 2 : Création de la fiche Employee (RH)
            $employeeData = array_merge($validated, ['user_id' => $user->id]);
            $employee = Employee::create($employeeData);

            // ÉTAPE 3 : Synchronisation des rôles (si présent)
            if (!empty($validated['role_ids'])) {
                $employee->roles()->sync($validated['role_ids']);
            }

            $employee->load(['department', 'roles']);

            return response()->json([
                'message'  => 'Employé et compte utilisateur créés avec succès.',
                'employee' => $employee
            ], 201);
        } catch (\Throwable $e) {
            Log::error("Erreur dans store(): ".$e->getMessage());
            return response()->json([
                "message" => "Erreur interne",
                "error"   => $e->getMessage()
            ], 500);
        }
    }


    // Affichage d'un employé (OK)
    public function show(Employee $employee)
    {
        try {
            $employee->load(['department', 'roles', 'tasks', 'presences', 'leaveRequests']);
            return response()->json($employee);
        } catch (Throwable $e) {
            Log::error("Erreur dans show(): ".$e->getMessage());
            return response()->json(["message" => "Erreur interne"], 500);
        }
    }

    /**
     * Mise à jour d'un employé (Méthode Admin) - CORRIGÉ
     */
    public function update(Request $request, Employee $employee)
    {
        try {
            $validated = $request->validate([
                'first_name'    => 'sometimes|required|string|max:255',
                'last_name'     => 'sometimes|required|string|max:255',
                // 🛠️ Correction : S'assurer que l'email est unique SAUF pour l'User lié actuel et l'Employee actuel
                'email'         => [
                    'sometimes',
                    'required',
                    'email',
                    'unique:employees,email,' . $employee->id,
                    'unique:users,email,' . ($employee->user_id ?? 'NULL'), // Exclut l'User ID de la vérification
                ],
                'phone'         => 'nullable|string',
                'contract_type' => 'sometimes|required|string',
                'hire_date'     => 'sometimes|required|date|date_format:Y-m-d',
                'salary_base'   => 'sometimes|required|numeric',
                'department_id' => 'nullable|exists:departments,id',
                'role_ids'      => 'nullable|array',
                'role_ids.*'    => 'exists:roles,id',
            ]);

            // ----------------------------------------------------
            // 🎯 NOUVEAU : Synchronisation du compte User
            // ----------------------------------------------------
            $user = $employee->user;
            
            if ($user) {
                // 1. Mise à jour de l'Email si elle est dans la requête
                if (isset($validated['email']) && $user->email !== $validated['email']) {
                    $user->email = $validated['email'];
                }
                
                // 2. Mise à jour du Nom
                if (isset($validated['first_name']) || isset($validated['last_name'])) {
                    $user->name = ($validated['first_name'] ?? $employee->first_name) . ' ' . ($validated['last_name'] ?? $employee->last_name);
                }
                
                // Sauvegarder les changements dans la table Users si nécessaire
                if ($user->isDirty()) {
                    $user->save();
                }
            }
            // ----------------------------------------------------

            $employee->update($validated);

            if (isset($validated['role_ids'])) { 
                $employee->roles()->sync($validated['role_ids']);
            }

            $employee->load(['department', 'roles']);
            return response()->json($employee);

        } catch (Throwable $e) {
            Log::error("Erreur dans update(): ".$e->getMessage());
            return response()->json(["message" => "Erreur interne", "error" => $e->getMessage()], 500);
        }
    }

    /**
     * Suppression d'un employé (Méthode Admin) - CORRIGÉ
     */
    public function destroy(Employee $employee)
    {
        try {
            // ----------------------------------------------------
            // 🎯 NOUVEAU : Suppression du compte User lié
            // ----------------------------------------------------
            if ($employee->user_id) {
                // Utilise la relation définie dans le modèle Employee
                $employee->user()->delete(); 
            }
            // ----------------------------------------------------

            $employee->delete();
            return response()->json(null, 204);
        } catch (Throwable $e) {
            Log::error("Erreur dans destroy(): ".$e->getMessage());
            return response()->json(["message" => "Erreur interne"], 500);
        }
    }
}
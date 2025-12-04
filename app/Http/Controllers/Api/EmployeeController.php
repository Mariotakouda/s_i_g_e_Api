<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Task;
use App\Models\Presence;
use App\Models\LeaveRequest;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmployeeController extends Controller
{
    
    //  PROFIL EMPLOYÉ CONNECTÉ
      
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

    
    //  TÂCHES DE L'EMPLOYÉ
      
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

    
    //  PRÉSENCES
      
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

    
    //  DEMANDES DE CONGÉ
      
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

    
    //  ANNONCES VISIBLES PAR L'EMPLOYÉ
      
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

    
    //  DEPARTEMENT DE L'EMPLOYÉ
      
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

    
    //  ROLES DE L'EMPLOYÉ
      
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

    
    //  HISTORIQUE DE DEMANDES DE CONGÉS
      
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
     * Liste des employés (admin) - Utilisé par les formulaires de Manager, Task, etc.
     */
    public function index(): JsonResponse 
    {
        try {
            // 🎯 CORRECTION 1: Ajout de 'email' qui est nécessaire pour l'affichage dans le frontend React
            $employees = Employee::select('id', 'first_name', 'last_name', 'email')
                ->orderBy('last_name')
                ->get();

            // 🎯 CORRECTION 2: Envelopper les résultats dans la clé 'data' 
            // pour correspondre à l'attente du frontend (employeesRes.data.data)
            return response()->json(['data' => $employees], 200); 
        } catch (Throwable $e) {
            Log::error("Erreur dans index(): " . $e->getMessage());

            return response()->json([
                "message" => "Erreur interne lors de la récupération des employés."
            ], 500); 
        }
    }

    // Création d'un employé
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'first_name'    => 'required|string|max:255',
                'last_name'     => 'required|string|max:255',
                'email'         => 'required|email|unique:employees,email',
                'phone'         => 'nullable|string',
                'contract_type' => 'required|string',
                'hire_date'     => 'required|date|date_format:Y-m-d',
                'salary_base'   => 'required|numeric',
                'department_id' => 'nullable|exists:departments,id',
                'role_ids'      => 'nullable|array',
                'role_ids.*'    => 'exists:roles,id',
            ]);

            $employee = Employee::create($validated);

            if (!empty($validated['role_ids'])) {
                $employee->roles()->sync($validated['role_ids']);
            }

            return response()->json($employee, 201);
        } catch (Throwable $e) {
            Log::error("Erreur dans store(): ".$e->getMessage());
            return response()->json(["message" => "Erreur interne", "error" => $e->getMessage()], 500);
        }
    }

    // Affichage d'un employé
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

    // Mise à jour d'un employé
    public function update(Request $request, Employee $employee)
    {
        try {
            $validated = $request->validate([
                'first_name'    => 'sometimes|required|string|max:255',
                'last_name'     => 'sometimes|required|string|max:255',
                'email'         => 'sometimes|required|email|unique:employees,email,' . $employee->id,
                'phone'         => 'nullable|string',
                'contract_type' => 'sometimes|required|string',
                'hire_date'     => 'sometimes|required|date|date_format:Y-m-d',
                'salary_base'   => 'sometimes|required|numeric',
                'department_id' => 'nullable|exists:departments,id',
                'role_ids'      => 'nullable|array',
                'role_ids.*'    => 'exists:roles,id',
            ]);

            $employee->update($validated);

            if (!empty($validated['role_ids'])) {
                $employee->roles()->sync($validated['role_ids']);
            }

            return response()->json($employee);
        } catch (Throwable $e) {
            Log::error("Erreur dans update(): ".$e->getMessage());
            return response()->json(["message" => "Erreur interne", "error" => $e->getMessage()], 500);
        }
    }

    // Suppression d'un employé
    public function destroy(Employee $employee)
    {
        try {
            $employee->delete();
            return response()->json(null, 204);
        } catch (Throwable $e) {
            Log::error("Erreur dans destroy(): ".$e->getMessage());
            return response()->json(["message" => "Erreur interne"], 500);
        }
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Throwable;

class TaskController extends Controller
{

    /**
     * ✅ Récupérer les tâches de l'employé connecté
     */
    public function myTasks()
    {
        try {
            $user = Auth::user();

            // Vérifier si l'utilisateur a un profil employé
            if (!$user->employee) {
                return response()->json(['data' => []], 200);
            }

            $tasks = Task::with(['employee.department', 'creator'])
                ->where('employee_id', $user->employee->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json(['data' => $tasks], 200);
        } catch (\Throwable $e) {
            Log::error("Erreur myTasks: " . $e->getMessage());
            return response()->json(["message" => "Erreur lors du chargement de vos tâches"], 500);
        }
    }
    /**
     * ✅ NOUVELLE MÉTHODE : Tâches de l'équipe pour le manager
     */
    public function managerTeamTasks()
    {
        try {
            $user = Auth::user();
            $userRole = strtolower($user->role);

            Log::info('👥 managerTeamTasks - Début', [
                'user_id' => $user->id,
                'user_role' => $user->role,
                'has_employee' => $user->employee ? true : false
            ]);

            // Vérifier si c'est un manager
            $isManager = false;
            $isAdmin = $userRole === 'admin';

            if ($userRole === 'manager') {
                $isManager = true;
            } elseif ($user->employee) {
                $hasManagerRole = $user->employee->roles()
                    ->whereRaw('LOWER(name) = ?', ['manager'])
                    ->exists();

                $existsInManagersTable = \App\Models\Manager::where('employee_id', $user->employee->id)->exists();

                $isManager = $hasManagerRole || $existsInManagersTable;
            }

            if (!$isManager && !$isAdmin) {
                return response()->json([
                    'data' => [],
                    'message' => 'Accès réservé aux managers.'
                ], 403);
            }

            // Récupérer le département du manager
            if (!$user->employee || !$user->employee->department_id) {
                return response()->json([
                    'data' => [],
                    'message' => 'Erreur : Vous n\'avez pas de département assigné.'
                ], 200);
            }

            $managerDeptId = $user->employee->department_id;
            $managerEmployeeId = $user->employee->id;

            Log::info('🎯 Filtrage tâches équipe', [
                'department_id' => $managerDeptId,
                'manager_employee_id' => $managerEmployeeId
            ]);

            // Récupérer toutes les tâches du département (sauf celles du manager)
            $tasks = Task::with(['employee.department', 'creator'])
                ->whereHas('employee', function ($q) use ($managerDeptId, $managerEmployeeId) {
                    $q->where('department_id', $managerDeptId)
                        ->where('id', '!=', $managerEmployeeId);
                })
                ->orderBy('created_at', 'desc')
                ->get();

            // Grouper par statut
            $tasksByStatus = [
                'pending' => $tasks->where('status', 'pending')->values(),
                'in_progress' => $tasks->where('status', 'in_progress')->values(),
                'completed' => $tasks->where('status', 'completed')->values(),
                'cancelled' => $tasks->where('status', 'cancelled')->values(),
            ];

            Log::info('✅ Tâches équipe récupérées', [
                'total' => $tasks->count(),
                'pending' => $tasksByStatus['pending']->count(),
                'in_progress' => $tasksByStatus['in_progress']->count(),
                'completed' => $tasksByStatus['completed']->count()
            ]);

            return response()->json([
                'data' => $tasks,
                'grouped' => $tasksByStatus,
                'stats' => [
                    'total' => $tasks->count(),
                    'pending' => $tasksByStatus['pending']->count(),
                    'in_progress' => $tasksByStatus['in_progress']->count(),
                    'completed' => $tasksByStatus['completed']->count(),
                    'cancelled' => $tasksByStatus['cancelled']->count(),
                ]
            ], 200);
        } catch (Throwable $e) {
            Log::error("❌ Erreur dans managerTeamTasks", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(["message" => "Erreur interne"], 500);
        }
    }

    /**
     * ✅ Liste des tâches avec filtrage selon le rôle
     */
    public function index()
    {
        try {
            $user = Auth::user();

            $query = Task::with(['employee.department', 'creator']);

            // FILTRAGE PAR RÔLE
            if ($user->role === 'manager' || $this->isEmployeeManager($user)) {
                $managerDeptId = $user->employee->department_id ?? null;

                if (!$managerDeptId) {
                    return response()->json([
                        'data' => [],
                        'message' => 'Manager sans département assigné'
                    ], 200);
                }

                $query->whereHas('employee', function ($q) use ($managerDeptId) {
                    $q->where('department_id', $managerDeptId);
                });
            } elseif ($user->role === 'employee') {
                if ($user->employee) {
                    $query->where('employee_id', $user->employee->id);
                }
            }

            $tasks = $query->orderBy('created_at', 'desc')->paginate(15);
            return response()->json($tasks, 200);
        } catch (Throwable $e) {
            Log::error("Erreur index tâches: " . $e->getMessage());
            return response()->json(["message" => "Erreur interne"], 500);
        }
    }

    /**
     * ✅ Création avec validation stricte du département + Fichier PDF
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:pending,in_progress,completed,cancelled',
            'employee_id' => 'required|exists:employees,id',
            'due_date' => 'nullable|date',
            'task_file' => 'nullable|file|mimes:pdf|max:10240'
        ]);

        try {
            $targetEmployee = Employee::findOrFail($validated['employee_id']);

            // VALIDATION : Manager ne peut assigner qu'à son département
            if ($user->role === 'manager' || $this->isEmployeeManager($user)) {
                $managerDeptId = $user->employee->department_id ?? null;

                if (!$managerDeptId) {
                    return response()->json([
                        "message" => "Vous n'avez pas de département assigné."
                    ], 403);
                }

                if ($targetEmployee->department_id !== $managerDeptId) {
                    return response()->json([
                        "message" => "Vous ne pouvez assigner des tâches qu'aux employés de votre département."
                    ], 403);
                }
            }

            // Upload du fichier
            $path = null;
            if ($request->hasFile('task_file')) {
                $path = $request->file('task_file')->store('task_consignes', 'public');
            }

            $task = Task::create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'],
                'employee_id' => $validated['employee_id'],
                'due_date' => $validated['due_date'],
                'task_file' => $path,
                'creator_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Mission créée avec succès',
                'data' => $task->load(['employee.department', 'creator'])
            ], 201);
        } catch (Throwable $e) {
            Log::error("Erreur création tâche: " . $e->getMessage());
            return response()->json([
                "message" => "Erreur lors de la création",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ NOUVEAU : Téléchargement sécurisé du fichier de consignes
     */
    public function downloadTaskFile(Task $task)
    {
        try {
            $user = Auth::user();
            if (!$task->task_file || !Storage::disk('public')->exists($task->task_file)) {
                return response()->json(['message' => 'Fichier introuvable'], 404);
            }

            return Storage::disk('public')->download(
                $task->task_file,
                "consignes-mission-{$task->id}.pdf"
            );
        } catch (Throwable $e) {
            return response()->json(["message" => "Erreur de téléchargement"], 500);
        }
    }

    /**
     * ✅ NOUVEAU : Téléchargement sécurisé du rapport soumis
     */
    public function downloadReportFile(Task $task)
    {
        try {
            if (!$task->report_file || !Storage::disk('public')->exists($task->report_file)) {
                return response()->json(['message' => 'Rapport introuvable'], 404);
            }

            return Storage::disk('public')->download(
                $task->report_file,
                "rapport-mission-{$task->id}.pdf"
            );
        } catch (Throwable $e) {
            return response()->json(["message" => "Erreur de téléchargement"], 500);
        }
    }

    /**
     * ✅ Soumission de rapport par l'employé
     */
    public function submitReport(Request $request, Task $task)
    {
        $user = Auth::user();

        if (!$user->employee || $task->employee_id !== $user->employee->id) {
            return response()->json(["message" => "Accès refusé"], 403);
        }

        $request->validate(['report_file' => 'required|file|mimes:pdf|max:10240']);

        try {
            if ($task->report_file) {
                Storage::disk('public')->delete($task->report_file);
            }

            $path = $request->file('report_file')->store('task_reports', 'public');

            $task->update([
                'report_file' => $path,
                'status' => 'completed'
            ]);

            return response()->json([
                'message' => 'Rapport soumis avec succès',
                'data' => $task->load(['employee.department', 'creator'])
            ]);
        } catch (Throwable $e) {
            Log::error("Erreur soumission rapport: " . $e->getMessage());
            return response()->json(["message" => "Erreur soumission rapport"], 500);
        }
    }

    public function markAsCompleted(Request $request, Task $task)
    {
        $user = Auth::user();
        if (!$user->employee || $task->employee_id !== $user->employee->id) {
            return response()->json(["message" => "Accès refusé"], 403);
        }
        if (!$task->report_file) {
            return response()->json(["message" => "Rapport requis"], 400);
        }

        try {
            $task->update(['status' => 'completed']);
            return response()->json(['message' => 'Terminée', 'data' => $task]);
        } catch (Throwable $e) {
            return response()->json(["message" => "Erreur"], 500);
        }
    }

    public function update(Request $request, Task $task)
    {
        $user = Auth::user();
        if ($user->role === 'manager' || $this->isEmployeeManager($user)) {
            $managerDeptId = $user->employee->department_id ?? null;
            if (!$managerDeptId || $task->employee->department_id !== $managerDeptId) {
                return response()->json(["message" => "Accès refusé"], 403);
            }
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:pending,in_progress,completed,cancelled',
            'employee_id' => 'sometimes|exists:employees,id',
            'due_date' => 'nullable|date',
            'task_file' => 'nullable|file|mimes:pdf|max:10240'
        ]);

        try {
            if ($request->hasFile('task_file')) {
                if ($task->task_file) Storage::disk('public')->delete($task->task_file);
                $validated['task_file'] = $request->file('task_file')->store('task_consignes', 'public');
            }
            $task->update($validated);
            return response()->json(['message' => 'Mise à jour réussie', 'data' => $task->load(['employee.department', 'creator'])]);
        } catch (Throwable $e) {
            return response()->json(["message" => "Erreur"], 500);
        }
    }

    public function show(Task $task)
    {
        try {
            $user = Auth::user();

            // On charge les relations pour éviter que les badges de département soient vides
            $task->load(['employee.department', 'creator']);

            return response()->json([
                'success' => true,
                'data' => $task
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                "success" => false,
                "message" => "Erreur lors du chargement de la tâche"
            ], 500);
        }
    }

    public function destroy(Task $task)
    {
        $user = Auth::user();
        $task->load('employee');

        // L'appel à $this->isEmployeeManager fonctionne si la méthode est dans la classe
        $isAdmin = ($user->role === 'admin');
        $isManager = ($user->role === 'manager' || $this->isEmployeeManager($user));

        // ... (votre logique d'autorisation définie précédemment)

        $task->delete();
        return response()->json(["message" => "Supprimée"], 200);
    }

    /**
     * ✅ CETTE MÉTHODE DOIT ÊTRE ICI
     * Juste avant la fin de la classe TaskController
     */
    private function isEmployeeManager($user): bool
    {
        if (!$user || !$user->employee) {
            return false;
        }
        return $user->employee->roles()->where('name', 'manager')->exists();
    }
}

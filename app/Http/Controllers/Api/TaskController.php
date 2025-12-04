<?php

namespace App\Http\Controllers\Api;

use App\Models\Task;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class TaskController extends Controller
{
    /**
     * Liste toutes les tâches avec pagination et recherche
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Task::with('employee:id,first_name,last_name');

            // Recherche
            if ($request->has('search') && $request->search != '') {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Tri par date de création (plus récent d'abord)
            $query->orderBy('created_at', 'desc');

            // Pagination
            $perPage = $request->get('per_page', 15);
            $tasks = $query->paginate($perPage);

            return response()->json([
                'data' => $tasks->items(),
                'meta' => [
                    'current_page' => $tasks->currentPage(),
                    'last_page' => $tasks->lastPage(),
                    'per_page' => $tasks->perPage(),
                    'total' => $tasks->total(),
                    'prev_page_url' => $tasks->previousPageUrl(),
                    'next_page_url' => $tasks->nextPageUrl(),
                ]
            ]);
        } catch (\Throwable $th) {
            Log::error('Erreur liste tâches: ' . $th->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la récupération des tâches.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Créer une nouvelle tâche
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Log pour déboguer
            Log::info('Données reçues pour création:', $request->all());

            // Validation
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'required|in:pending,in_progress,completed',
                'employee_id' => 'nullable|integer|exists:employees,id',
                'due_date' => 'nullable|date',
            ]);

            Log::info('Données validées:', $validated);

            // Création
            $task = Task::create($validated);

            // Charger la relation employee
            $task->load('employee:id,first_name,last_name');

            return response()->json($task, 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Erreur validation:', $e->errors());
            return response()->json([
                'message' => 'Erreur de validation des données.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $th) {
            Log::error('Erreur création tâche: ' . $th->getMessage());
            Log::error('Stack trace: ' . $th->getTraceAsString());
            return response()->json([
                'message' => 'Erreur lors de la création de la tâche.',
                'error' => $th->getMessage(),
                'trace' => config('app.debug') ? $th->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Afficher une tâche spécifique
     */
    public function show(Task $task): JsonResponse
    {
        try {
            $task->load('employee:id,first_name,last_name');
            return response()->json($task);
        } catch (\Throwable $th) {
            Log::error('Erreur affichage tâche: ' . $th->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la récupération de la tâche.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour la tâche spécifiée
     */
    public function update(Request $request, Task $task): JsonResponse
    {
        try {
            Log::info('Données reçues pour mise à jour:', $request->all());

            $validated = $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'sometimes|required|in:pending,in_progress,completed',
                'employee_id' => 'nullable|integer|exists:employees,id',
                'due_date' => 'nullable|date',
            ]);

            Log::info('Données validées:', $validated);

            $task->update($validated);
            $task->load('employee:id,first_name,last_name');

            return response()->json($task);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Erreur validation update:', $e->errors());
            return response()->json([
                'message' => 'Erreur de validation des données.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $th) {
            Log::error('Erreur mise à jour tâche: ' . $th->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la mise à jour de la tâche.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer la tâche spécifiée
     */
    public function destroy(Task $task): JsonResponse
    {
        try {
            $task->delete();
            return response()->json(['message' => 'Tâche supprimée avec succès.'], 200);
        } catch (\Throwable $th) {
            Log::error('Erreur suppression tâche: ' . $th->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la suppression de la tâche.',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
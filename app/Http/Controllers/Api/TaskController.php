<?php

namespace App\Http\Controllers\Api;

use App\Models\Task;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class TaskController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $tasks = Task::with('employee:id,first_name,last_name')->get();
            return response()->json($tasks);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des tâches.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            // Convertit une date au format JJ/MM/AAAA vers un vrai format MySQL
            $data = $request->all();
            if (!empty($data['due_date'])) {
                try {
                    $data['due_date'] = Carbon::createFromFormat('d/m/Y', $data['due_date'])->format('Y-m-d');
                } catch (\Exception $e) {
                    // si le format est déjà correct (Y-m-d), on ne fait rien
                }
            }

            $validated = validator($data, [
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'required|string|in:pending,in_progress,completed,en cours,terminée',
                'due_date' => 'nullable|date',
                'employee_id' => 'required|exists:employees,id',
            ])->validate();

            // Harmonise les statuts français vers anglais si besoin
            $validated['status'] = match ($validated['status']) {
                'en cours' => 'in_progress',
                'terminée' => 'completed',
                default => $validated['status'],
            };

            $task = Task::create($validated);

            return response()->json($task, 201);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la création de la tâche.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function show(Task $task): JsonResponse
    {
        try {
            $task->load('employee');
            return response()->json($task);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération de la tâche.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, Task $task): JsonResponse
    {
        try {
            $data = $request->all();
            if (!empty($data['due_date'])) {
                try {
                    $data['due_date'] = Carbon::createFromFormat('d/m/Y', $data['due_date'])->format('Y-m-d');
                } catch (\Exception $e) {
                }
            }

            $validated = validator($data, [
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'sometimes|required|string|in:pending,in_progress,completed,en cours,terminée',
                'due_date' => 'nullable|date',
                'employee_id' => 'sometimes|required|exists:employees,id',
            ])->validate();

            $validated['status'] = match ($validated['status'] ?? null) {
                'en cours' => 'in_progress',
                'terminée' => 'completed',
                default => $validated['status'] ?? $task->status,
            };

            $task->update($validated);

            return response()->json($task);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour de la tâche.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function destroy(Task $task): JsonResponse
    {
        try {
            $task->delete();
            return response()->json(null, 204);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la suppression de la tâche.',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}

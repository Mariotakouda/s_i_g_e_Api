<?php

namespace App\Http\Controllers\Api;

use App\Models\Manager;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ManagerController extends Controller
{
    /**
     * Lister tous les managers
     */
    public function index(): JsonResponse
    {
        try {
            $managers = Manager::with('employee:id,first_name,last_name', 'managedDepartments')->get();
            return response()->json($managers, 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des managers.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Créer un manager
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'full_name' => 'required|string|max:255',
                'email' => 'required|email|unique:managers,email',
                'phone' => 'nullable|string',
                'employee_id' => 'required|exists:employees,id|unique:managers,employee_id',
                'department_id' => 'nullable|exists:departments,id',
            ]);

            $manager = Manager::create($validated);

            $manager->load('employee', 'managedDepartments');

            return response()->json($manager, 201);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la création du manager.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher un manager spécifique
     */
    public function show(Manager $manager): JsonResponse
    {
        try {
            $manager->load(['employee', 'managedDepartments']);
            return response()->json($manager, 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération du manager.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour un manager
     */
    public function update(Request $request, Manager $manager): JsonResponse
    {
        try {
            $validated = $request->validate([
                'full_name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|unique:managers,email,' . $manager->id,
                'phone' => 'nullable|string',
                'department_id' => 'nullable|exists:departments,id',
            ]);

            $manager->update($validated);

            $manager->load('employee', 'managedDepartments');

            return response()->json($manager, 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour du manager.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer un manager
     */
    public function destroy(Manager $manager): JsonResponse
    {
        try {
            $manager->delete();
            return response()->json(null, 204);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la suppression du manager.',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}

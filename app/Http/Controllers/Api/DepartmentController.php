<?php

namespace App\Http\Controllers\Api;

use App\Models\Department;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class DepartmentController extends Controller
{
    /**
     * Affiche la liste de tous les départements
     */
    public function index(): JsonResponse
    {
        try {
            return response()->json(Department::all(), 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des départements.',
                'error' => $th->getMessage(), // ✅ affichage de l’erreur exacte
            ], 500);
        }
    }

    /**
     * Crée un nouveau département
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|unique:departments,name',
                'description' => 'nullable|string',
            ]);

            $department = Department::create($validated);

            return response()->json($department, 201);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la création du département.',
                'error' => $th->getMessage(), // ✅ affichage de l’erreur exacte
            ], 500);
        }
    }

    /**
     * Affiche un département spécifique
     */
    public function show(Department $department): JsonResponse
    {
        try {
            $department->load('employees');
            return response()->json($department);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération du département.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Met à jour un département
     */
    public function update(Request $request, Department $department): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|required|string|unique:departments,name,' . $department->id,
                'description' => 'nullable|string',
            ]);

            $department->update($validated);
            return response()->json($department);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour du département.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Supprime un département
     */
    public function destroy(Department $department): JsonResponse
    {
        try {
            $department->delete();
            return response()->json(null, 204);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la suppression du département.',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}

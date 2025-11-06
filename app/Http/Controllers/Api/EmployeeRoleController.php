<?php

namespace App\Http\Controllers\Api;

use App\Models\EmployeeRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EmployeeRoleController extends Controller
{
    /**
     * Affiche toutes les attributions employé ↔ rôle
     */
    public function index(): JsonResponse
    {
        try {
            $employeeRoles = EmployeeRole::with([
                'employee:id,first_name,last_name',
                'role:id,name'
            ])->get();

            return response()->json($employeeRoles, 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des rôles employés.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Crée une nouvelle attribution employé ↔ rôle
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'role_id' => 'required|exists:roles,id',
            ]);

            // Vérifie si l'association existe déjà
            $exists = EmployeeRole::where('employee_id', $validated['employee_id'])
                ->where('role_id', $validated['role_id'])
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'Cette attribution de rôle existe déjà.'
                ], 409);
            }

            $employeeRole = EmployeeRole::create($validated);

            $employeeRole->load([
                'employee:id,first_name,last_name',
                'role:id,name'
            ]);

            return response()->json($employeeRole, 201);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la création de l’attribution.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Supprime une attribution employé ↔ rôle
     */
    public function destroy(int $employee_id, int $role_id): JsonResponse
    {
        try {
            $deleted = EmployeeRole::where('employee_id', $employee_id)
                ->where('role_id', $role_id)
                ->delete();

            if ($deleted) {
                return response()->json(null, 204);
            }

            return response()->json([
                'message' => 'Association non trouvée.'
            ], 404);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la suppression de l’attribution.',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}

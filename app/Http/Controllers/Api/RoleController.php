<?php

namespace App\Http\Controllers\Api;

use App\Models\Role;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $roles = Role::withCount('employees')->paginate(10);
            return response()->json($roles);
        } catch (\Throwable $th) {
            return response()->json([
            'message' => 'Erreur lors de la récupération des rôles.',
            'error' => $th->getMessage(),
        ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|unique:roles,name',
            ]);

            $role = Role::create($validated);
            return response()->json($role, 201);

        } catch (ValidationException $e) {
            return response()->json(['erreurs' => $e->errors()], 422);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Erreur lors de la création du rôle.'], 500);
        }
    }

    public function show(Role $role): JsonResponse
    {
        try {
            $role->load('employees');
            return response()->json($role);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Erreur lors de la récupération du rôle.'], 500);
        }
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|required|string|unique:roles,name,' . $role->id,
            ]);

            $role->update($validated);
            return response()->json($role);

        } catch (ValidationException $e) {
            return response()->json(['erreurs' => $e->errors()], 422);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Erreur lors de la mise à jour du rôle.'], 500);
        }
    }

    public function destroy(Role $role): JsonResponse
    {
        try {
            $role->delete();
            return response()->json(null, 204);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Erreur lors de la suppression du rôle.'], 500);
        }
    }
}

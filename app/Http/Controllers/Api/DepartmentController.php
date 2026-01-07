<?php

namespace App\Http\Controllers\Api;

use App\Models\Department;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class DepartmentController extends Controller
{
    /**
     * Récupère une liste simple de tous les départements
     * pour les listes déroulantes des formulaires.
     */
    public function list(): JsonResponse
    {
        try {
            $departments = Department::select('id', 'name')
                ->orderBy('name')
                ->get();

            return response()->json(['data' => $departments], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération de la liste des départements.',
                'error'   => $th->getMessage()
            ], 500);
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

    /**
     * Affiche la liste complète des départements
     */
    public function index(): JsonResponse
    {
        try {
            $departments = Department::all();
            return response()->json(['data' => $departments], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des départements.',
                'error'   => $th->getMessage()
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
                'name'        => 'required|string|unique:departments,name',
                'description' => 'nullable|string',
            ]);

            $department = Department::create($validated);

            return response()->json(['data' => $department], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors'  => $e->errors()
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la création du département.',
                'error'   => $th->getMessage()
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
            return response()->json(['data' => $department], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération du département.',
                'error'   => $th->getMessage()
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
                'name'        => 'sometimes|required|string|unique:departments,name,' . $department->id,
                'description' => 'nullable|string',
            ]);

            $department->update($validated);

            return response()->json(['data' => $department], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors'  => $e->errors()
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour du département.',
                'error'   => $th->getMessage()
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
                'error'   => $th->getMessage()
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Models\Employee;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class EmployeeController extends Controller
{
    /**
     * Affiche la liste paginée des employés avec leurs départements et rôles associés.
     */
    public function index(): JsonResponse
    {
        try {
            // Récupère les employés avec leurs relations "department" et "roles"
            $employees = Employee::with(['department', 'roles'])->paginate(15); 

            // Retourne les employés sous forme de JSON
            return response()->json(['employee' => $employees], 200);
        } catch (\Throwable $th) {
            // En cas d’erreur (ex : problème de base de données)
            return response()->json(['message' => 'Failed to fetch employees.'], 500);
        }
    }

    /**
     * Crée un nouvel employé à partir des données fournies dans la requête.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validation des champs du formulaire d’ajout
            $validated = $request->validate([
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'email' => 'nullable|email|unique:employees,email',
                'phone' => 'nullable|string',
                'contract_type' => 'nullable|string',
                'hire_date' => 'nullable|date',
                'salary_base' => 'nullable|numeric',
                'department_id' => 'nullable|exists:departments,id',
                'role_ids' => 'nullable|array',
                'role_ids.*' => 'exists:roles,id',
            ]);

            // Création de l’employé avec les données validées
            $employee = Employee::create($validated);

            // Charge les relations "roles" et "department" pour renvoyer un employé complet
            $employee->load('roles', 'department');

            // Retourne l’employé créé avec le code HTTP 201 (création réussie)
            return response()->json($employee, 201);
        } catch (\Throwable $th) {
            // En cas d’erreur, retourne une réponse d’erreur générique
            return response()->json(['message' => 'Failed to create employee.'], 500);
        }
    }

    /**
     * Affiche les détails d’un employé spécifique (avec ses relations).
     */
    public function show(Employee $employee): JsonResponse
    {
        try {
            // Charge les relations liées à l’employé
            $employee->load(['department', 'roles', 'tasks', 'presences', 'leaveRequests']);

            // Retourne l’employé et toutes ses informations associées
            return response()->json($employee);
        } catch (\Throwable $th) {
            // En cas d’échec, retourne une erreur
            return response()->json(['message' => 'Failed to retrieve employee.'], 500);
        }
    }

    /**
     * Met à jour un employé existant avec les nouvelles données fournies.
     */
    public function update(Request $request, Employee $employee): JsonResponse
    {
        try {
            // Validation des champs pouvant être mis à jour
            $validated = $request->validate([
                'first_name' => 'sometimes|nullable|string|max:255',
                'last_name' => 'sometimes|nullable|string|max:255',
                'email' => 'sometimes|nullable|email|unique:employees,email,' . $employee->id,
                'phone' => 'nullable|string',
                'contract_type' => 'sometimes|nullable|string',
                'hire_date' => 'sometimes|nullable|date',
                'salary_base' => 'sometimes|nullable|numeric',
                'department_id' => 'sometimes|nullable|exists:departments,id',
                'role_ids' => 'nullable|array',
                'role_ids.*' => 'exists:roles,id',
            ]);

            // Mise à jour des informations de l’employé
            $employee->update($validated);

            // Gestion des rôles :
            // - Si "role_ids" est défini → synchronise les rôles
            // - Si "role_ids" est null → vide les rôles
            if (isset($validated['role_ids'])) {
                $employee->roles()->sync($validated['role_ids']);
            } elseif (array_key_exists('role_ids', $request->all()) && is_null($validated['role_ids'])) {
                $employee->roles()->sync([]);
            }

            // Recharge les relations mises à jour
            $employee->load('roles', 'department');

            // Retourne l’employé mis à jour
            return response()->json($employee);
        } catch (\Throwable $th) {
            // En cas d’erreur, retourne une erreur générique
            return response()->json(['message' => 'Failed to update employee.'], 500);
        }
    }

    /**
     * Supprime un employé de la base de données.
     */
    public function destroy(Employee $employee): JsonResponse
    {
        try {
            // Suppression de l’employé
            $employee->delete();

            // Retourne une réponse vide avec code 204 (suppression réussie)
            return response()->json(null, 204);
        } catch (\Throwable $th) {
            // Gestion d’erreur en cas d’échec
            return response()->json(['message' => 'Failed to delete employee.'], 500);
        }
    }
}

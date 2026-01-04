<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Manager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB; // Ajouté pour les transactions
use Throwable;

class ManagerController extends Controller
{
    /**
     * Liste des managers avec pagination et recherche.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Manager::query();
            $query->with(['employee', 'department']);

            if ($request->has('search') && $request->search != '') {
                $search = $request->input('search');
                $query->whereHas('employee', function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $managers = $query->latest()->paginate(10); 
            return response()->json($managers);

        } catch (Throwable $e) {
            Log::error("Erreur dans ManagerController@index: " . $e->getMessage());
            return response()->json(["message" => "Erreur interne lors du chargement."], 500); 
        }
    }

    /**
     * Création d'un nouveau manager + Mise à jour du rôle utilisateur.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'employee_id'   => 'required|exists:employees,id|unique:managers,employee_id',
                'department_id' => 'nullable|exists:departments,id',
            ]);

            // Utilisation d'une transaction pour garantir que tout est mis à jour ou rien
            return DB::transaction(function () use ($validated) {
                $employee = Employee::findOrFail($validated['employee_id']);

                // 1. Préparer les données pour la table managers
                $validated['full_name'] = $employee->first_name . ' ' . $employee->last_name;
                $validated['email']     = $employee->email;

                $manager = Manager::create($validated);

                // 2. ⚡ SYNCHRONISATION DU RÔLE (CRUCIAL)
                // On récupère l'utilisateur lié à l'employé pour changer son rôle en 'manager'
                if ($employee->user) {
                    $employee->user->update(['role' => 'manager']);
                    Log::info("Rôle utilisateur mis à jour vers 'manager' pour l'ID: " . $employee->user_id);
                }

                $manager->load(['employee', 'department']);

                return response()->json([
                    'data'    => $manager,
                    'message' => 'Manager créé et rôle utilisateur mis à jour avec succès',
                ], 201);
            });

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error("Erreur dans ManagerController@store: " . $e->getMessage());
            return response()->json(["message" => "Erreur lors de la création du manager."], 500);
        }
    }

    /**
     * Affichage d'un manager spécifique.
     */
    public function show(Manager $manager): JsonResponse
    {
        try {
            $manager->load(['employee', 'department']);
            return response()->json(['data' => $manager]);
        } catch (Throwable $e) {
            return response()->json(["message" => "Erreur lors du chargement."], 500);
        }
    }

    /**
     * Mise à jour du manager.
     */
    public function update(Request $request, Manager $manager): JsonResponse
    {
        try {
            $validated = $request->validate([
                'department_id' => 'nullable|exists:departments,id',
            ]);

            $manager->update($validated);
            $manager->load(['employee', 'department']);

            return response()->json([
                'data' => $manager,
                'message' => 'Manager mis à jour avec succès'
            ]);
        } catch (Throwable $e) {
            return response()->json(["message" => "Erreur lors de la mise à jour."], 500);
        }
    }

    /**
     * Suppression d'un manager + Rétrogradation du rôle.
     */
    public function destroy(Manager $manager): JsonResponse
    {
        try {
            return DB::transaction(function () use ($manager) {
                $employee = $manager->employee;

                // 1. ⚡ RÉTROGRADATION DU RÔLE (OPTIONNEL)
                // Si on supprime le manager, il redevient un simple employé
                if ($employee && $employee->user) {
                    $employee->user->update(['role' => 'employee']);
                }

                $manager->delete();
                return response()->json(null, 204); 
            });
        } catch (Throwable $e) {
            Log::error("Erreur dans ManagerController@destroy: " . $e->getMessage());
            return response()->json(["message" => "Erreur lors de la suppression."], 500);
        }
    }
}
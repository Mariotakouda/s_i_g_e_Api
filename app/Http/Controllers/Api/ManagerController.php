<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Manager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ManagerController extends Controller
{
    /**
     * Liste des managers avec pagination et recherche.
     * CHARGE les relations 'employee' et 'department' requises par le frontend.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Manager::query();

            // 🎯 CRUCIAL : Charger les relations nécessaires
            $query->with(['employee', 'department']);

            // Gestion de la recherche
            if ($request->has('search') && $request->search != '') {
                $search = $request->input('search');
                $query->whereHas('employee', function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Application de la pagination
            $managers = $query->latest()->paginate(10); 
            
            return response()->json($managers);

        } catch (Throwable $e) {
            Log::error("Erreur dans ManagerController@index", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                "message" => "Erreur interne lors du chargement des managers."
            ], 500); 
        }
    }

    /**
     * Affichage d'un manager spécifique.
     * 🎯 CORRECTION : Structure de réponse cohérente
     */
    public function show(Manager $manager): JsonResponse
    {
        try {
            $manager->load(['employee', 'department']);
            
            Log::info("Manager show", [
                'id' => $manager->id,
                'employee_id' => $manager->employee_id,
                'department_id' => $manager->department_id
            ]);
            
            // 🎯 Retourner avec structure "data" pour cohérence
            return response()->json([
                'data' => $manager
            ]);
            
        } catch (Throwable $e) {
            Log::error("Erreur dans ManagerController@show", [
                'manager_id' => $manager->id ?? 'N/A',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                "message" => "Erreur interne lors du chargement du manager"
            ], 500);
        }
    }

    /**
 * Création d'un nouveau manager.
 */
public function store(Request $request): JsonResponse
{
    try {
        $validated = $request->validate([
            'employee_id'   => 'required|exists:employees,id|unique:managers,employee_id',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        // 🎯 2. CORRECTION CLÉ : Récupérer les données de l'employé
        // Ceci fournit 'full_name' et 'email' requis par la DB Manager
        $employee = Employee::findOrFail($validated['employee_id']);

        // Ajout des champs obligatoires à la requête de création
        $validated['full_name'] = $employee->first_name . ' ' . $employee->last_name;
        $validated['email']     = $employee->email;

        $manager = Manager::create($validated);
        $manager->load(['employee', 'department']);

        Log::info("Manager créé", ['id' => $manager->id]);

        return response()->json([
            'data'    => $manager,
            'message' => 'Manager créé avec succès',
        ], 201);

    } catch (\Illuminate\Validation\ValidationException $e) {
        Log::warning("Validation échouée pour création manager", [
            'errors' => $e->errors(),
        ]);
        throw $e;

    } catch (\Throwable $e) {
        Log::error("Erreur dans ManagerController@store", [
            'message' => $e->getMessage(),
            'trace'   => $e->getTraceAsString(),
        ]);

        return response()->json([
            'message' => "Erreur interne lors de la création du manager.",
        ], 500);
    }
}


    /**
     * Mise à jour du manager (principalement le département géré).
     * 🎯 CORRECTION : Validation et gestion des erreurs améliorées
     */
    public function update(Request $request, Manager $manager): JsonResponse
    {
        try {
            Log::info("Tentative de mise à jour manager", [
                'manager_id' => $manager->id,
                'request_data' => $request->all()
            ]);
            
            $validated = $request->validate([
                'department_id' => 'nullable|exists:departments,id',
            ]);

            $manager->update($validated);
            $manager->load(['employee', 'department']);

            Log::info("Manager mis à jour", [
                'id' => $manager->id,
                'department_id' => $manager->department_id
            ]);

            return response()->json([
                'data' => $manager,
                'message' => 'Manager mis à jour avec succès'
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning("Validation échouée pour mise à jour manager", [
                'manager_id' => $manager->id,
                'errors' => $e->errors()
            ]);
            throw $e;
            
        } catch (Throwable $e) {
            Log::error("Erreur dans ManagerController@update", [
                'manager_id' => $manager->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                "message" => "Erreur interne lors de la mise à jour."
            ], 500);
        }
    }

    /**
     * Suppression d'un manager.
     */
    public function destroy(Manager $manager): JsonResponse
    {
        try {
            $managerId = $manager->id;
            $manager->delete();
            
            Log::info("Manager supprimé", ['id' => $managerId]);
            
            return response()->json(null, 204); 
            
        } catch (Throwable $e) {
            Log::error("Erreur dans ManagerController@destroy", [
                'manager_id' => $manager->id ?? 'N/A',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                "message" => "Erreur interne lors de la suppression."
            ], 500);
        }
    }
}
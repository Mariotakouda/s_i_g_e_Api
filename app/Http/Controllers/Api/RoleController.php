<?php

namespace App\Http\Controllers\Api;

use App\Models\Role;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    /**
     * Liste des rôles avec pagination et recherche.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $search = $request->query('search'); 

            $roles = Role::query()
                ->when($search, function ($query, $search) {
                    return $query->where('name', 'like', '%' . $search . '%');
                })
                ->withCount('employees') 
                ->paginate(10);
                
            return response()->json($roles);

        } catch (\Throwable $th) {
            // Loggez l'erreur pour le débogage
            \Log::error('Erreur lors de la récupération des rôles: ' . $th->getMessage());
            return response()->json([
            'message' => 'Erreur lors de la récupération des rôles.',
            'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Crée un nouveau rôle.
     */
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
            \Log::error('Erreur lors de la création du rôle: ' . $th->getMessage());
            return response()->json(['message' => 'Erreur lors de la création du rôle.'], 500);
        }
    }

    /**
     * Affiche un rôle spécifique (avec le compte d'employés).
     */
    public function show(Role $role): JsonResponse
    {
        try {
            // CORRECTION: Utilisation de loadCount pour ajouter 'employees_count'
            $role->loadCount('employees'); 
            // La ligne suivante charge la collection complète, utile si on veut lister les employés
            // $role->load('employees'); 
            
            return response()->json($role);
        } catch (\Throwable $th) {
            \Log::error('Erreur lors de la récupération du rôle: ' . $th->getMessage());
            return response()->json(['message' => 'Erreur lors de la récupération du rôle.'], 500);
        }
    }

    /**
     * Met à jour un rôle spécifique.
     */
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
            \Log::error('Erreur lors de la mise à jour du rôle: ' . $th->getMessage());
            return response()->json(['message' => 'Erreur lors de la mise à jour du rôle.'], 500);
        }
    }

    /**
     * Supprime un rôle spécifique.
     */
    public function destroy(Role $role): JsonResponse
    {
        try {
            $role->delete();
            return response()->json(null, 204); 
        } catch (\Throwable $th) {
            \Log::error('Erreur lors de la suppression du rôle: ' . $th->getMessage());
            return response()->json(['message' => 'Erreur lors de la suppression du rôle.'], 500);
        }
    }
}
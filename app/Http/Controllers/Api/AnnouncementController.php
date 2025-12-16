<?php

namespace App\Http\Controllers\Api;

use App\Models\Announcement;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnnouncementController extends Controller
{
   public function index(): JsonResponse
    {
        try {
            $announcements = Announcement::with(['employee:id,first_name,last_name', 'department:id,name'])
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            return response()->json($announcements);
        } catch (Throwable $th) {
            Log::error("Erreur dans AnnouncementController@index", ['details' => $th->getMessage()]);
            return response()->json([
                'error' => 'Failed to fetch announcements.',
                'details' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Création d'une nouvelle annonce (Corrigé pour user_id).
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // 1. Validation des données
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'message' => 'required|string',
                // S'assurer que ces champs existent si l'admin sélectionne une cible
                'employee_id' => 'nullable|exists:employees,id',
                'department_id' => 'nullable|exists:departments,id',
                'is_general' => 'boolean',
            ]);

            // 🎯 CORRECTION CLÉ : Ajouter l'ID de l'utilisateur créateur
            if (Auth::check()) {
                $validated['user_id'] = Auth::id();
            } else {
                // Gérer le cas où l'utilisateur n'est pas authentifié (bien que la route doive être protégée)
                return response()->json(['message' => 'Non authentifié pour créer une annonce.'], 401);
            }

            // Assurer la cohérence des cibles
            if (isset($validated['is_general']) && $validated['is_general']) {
                 $validated['employee_id'] = null;
                 $validated['department_id'] = null;
            } elseif (isset($validated['department_id'])) {
                $validated['is_general'] = false;
                $validated['employee_id'] = null;
            } elseif (isset($validated['employee_id'])) {
                $validated['is_general'] = false;
                $validated['department_id'] = null;
            } else {
                // Si aucune cible n'est définie (ni general, ni department, ni employee)
                // On force en général par défaut, ou on renvoie une erreur. Forçons en général ici:
                 $validated['is_general'] = true;
            }

            // 2. Création du modèle
            $announcement = Announcement::create($validated);
            
            Log::info("Annonce créée", ['id' => $announcement->id, 'user_id' => $validated['user_id']]);

            return response()->json($announcement, 201);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
             return response()->json([
                'error' => 'Validation Failed.',
                'details' => $e->errors(),
            ], 422);
        } catch (Throwable $th) {
            Log::error("Erreur dans AnnouncementController@store", ['details' => $th->getMessage(), 'trace' => $th->getTraceAsString()]);
            return response()->json([
                'error' => 'Failed to create announcement.',
                'details' => $th->getMessage(),
            ], 500);
        }
    }

    public function show(Announcement $announcement): JsonResponse
    {
        try {
            $announcement->load(['employee', 'department']);
            return response()->json($announcement);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => 'Failed to retrieve announcement.',
                'details' => $th->getMessage(),
            ], 500);
        }
    }


    /**
     * NOUVEAU : Récupère les annonces pertinentes pour l'employé connecté.
     * Cette méthode est appelée par la route /me/announcements
     */
    public function fetchMyAnnouncements(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Récupérer l'employé lié à l'utilisateur
            // (Assurez-vous que votre relation Employee/User est correcte)
            $employee = Employee::where('user_id', $user->id)->first(); 

            if (!$employee) {
                // L'utilisateur est connecté mais n'est pas un employé (ex: un super admin sans profil employé)
                // On peut toujours retourner les annonces générales.
                $employeeId = null;
                $departmentId = null;
            } else {
                $employeeId = $employee->id;
                $departmentId = $employee->department_id;
            }

            // Construction de la requête de filtre
            $query = Announcement::where('is_general', true); // Générales toujours affichées

            if ($employeeId) {
                $query->orWhere('employee_id', $employeeId); // Annonces Personnelles
            }
            
            if ($departmentId) {
                $query->orWhere('department_id', $departmentId); // Annonces de Département
            }

            $announcements = $query
                ->with('employee:id,first_name,last_name', 'department:id,name')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($announcements);
            
        } catch (Throwable $th) {
            Log::error("Erreur dans AnnouncementController@fetchMyAnnouncements", ['details' => $th->getMessage()]);
            return response()->json([
                'error' => 'Failed to fetch employee announcements.',
                'details' => $th->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, Announcement $announcement): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'message' => 'sometimes|required|string',
                'employee_id' => 'nullable|exists:employees,id',
                'department_id' => 'nullable|exists:departments,id',
                'is_general' => 'boolean',
            ]);

            $announcement->update($validated);

            return response()->json($announcement);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => 'Failed to update announcement.',
                'details' => $th->getMessage(),
            ], 500);
        }
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        try {
            $announcement->delete();
            return response()->json(null, 204);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => 'Failed to delete announcement.',
                'details' => $th->getMessage(),
            ], 500);
        }
    }
}

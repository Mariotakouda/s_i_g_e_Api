<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class LeaveRequestController extends Controller
{
    /**
     * Affiche toutes les demandes de congé de l'employé connecté.
     */
    public function index(): JsonResponse
    {
        try {
            $employee = Auth::user()->employee;
            if (!$employee) {
                return response()->json(["message" => "Profil employé non trouvé."], 404);
            }
            
            // Récupère uniquement les demandes associées à l'employé
            $leaves = $employee->leaveRequests()->latest()->get(); 

            return response()->json($leaves);
            
        } catch (Throwable $e) {
            Log::error("Erreur dans LeaveRequestController@index: " . $e->getMessage());
            return response()->json(["message" => "Erreur interne"], 500);
        }
    }

    // ----------------------------------------------------
    // 🎯 NOUVEAU : Index pour le tableau de bord Admin
    // ----------------------------------------------------
    /**
     * Affiche TOUTES les demandes de congé (méthode Admin).
     * Inclut l'Eager Loading pour la relation 'employee'.
     */
    public function indexAdmin(): JsonResponse
    {
        try {
            // Vérification de l'autorisation ici (par exemple, Auth::user()->can('manage_leaves'))
            // Je suppose qu'un middleware gère déjà la permission 'admin'
            
            // 🚀 Chargement de TOUTES les demandes avec les relations (employee)
            $leaves = LeaveRequest::with('employee')
                ->latest()
                ->get(); 
            
            return response()->json($leaves);
            
        } catch (Throwable $e) {
            Log::error("Erreur dans LeaveRequestController@indexAdmin: " . $e->getMessage());
            // Il est préférable de retourner une erreur 500 générique en cas de plantage BDD/Serveur
            return response()->json(["message" => "Erreur lors du chargement des demandes d'administration."], 500);
        }
    }

    /**
     * Soumet une nouvelle demande de congé (par l'employé).
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $employee = Auth::user()->employee;

            if (!$employee) {
                return response()->json(["message" => "Profil employé non trouvé."], 404);
            }
            
            // Validation basée sur le modèle et la migration fournis (champ 'message')
            $validated = $request->validate([
                'type' => 'required|string', // Vous pouvez ajouter in:annuel,maladie,etc. si vous utilisez un enum
                'start_date' => 'required|date|after_or_equal:today',
                'end_date' => 'required|date|after_or_equal:start_date',
                'message' => 'nullable|string|max:500', // Correspond au champ 'message'
            ]);

            // Création de la demande
            $leaveRequest = LeaveRequest::create(array_merge($validated, [
                'employee_id' => $employee->id,
                'status' => 'pending', // Statut par défaut
            ]));

            return response()->json([
                'message' => 'Demande de congé soumise avec succès et en attente d\'approbation.',
                'request' => $leaveRequest
            ], 201);

        } catch (Throwable $e) {
            Log::error("Erreur dans LeaveRequestController@store: " . $e->getMessage());
            // Retourne les erreurs de validation si elles existent
            return response()->json(["message" => "Erreur lors de la soumission de la demande.", "error" => $e->getMessage()], 500);
        }
    }

    // ----------------------------------------------------
    // 🎯 NOUVEAU : Méthodes d'action Approve/Reject pour l'Admin
    // ----------------------------------------------------
    
    public function approve(LeaveRequest $leaveRequest): JsonResponse
    {
        $leaveRequest->update(['status' => 'approved']);
        // Recharger avec l'employé pour la réponse React
        $leaveRequest->load('employee'); 
        return response()->json($leaveRequest);
    }
    
    public function reject(LeaveRequest $leaveRequest): JsonResponse
    {
        $leaveRequest->update(['status' => 'rejected']);
        // Recharger avec l'employé pour la réponse React
        $leaveRequest->load('employee');
        return response()->json($leaveRequest);
    }
}

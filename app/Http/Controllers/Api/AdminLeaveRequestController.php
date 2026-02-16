<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdminLeaveRequestController extends Controller
{
    /**
     * Liste toutes les demandes de congé pour l'administrateur.
     */
    public function index(): JsonResponse
    {
        try {
            $requests = LeaveRequest::with('employee:id,first_name,last_name,email') 
                ->latest()
                ->get();

            return response()->json($requests);
        } catch (Throwable $e) {
            Log::error("Erreur dans AdminLeaveRequestController@index: " . $e->getMessage());
            return response()->json(["message" => "Erreur interne lors de la récupération des demandes."], 500);
        }
    }

    /**
     * Approuve une demande de congé.
     */
    public function approve(LeaveRequest $leaveRequest): JsonResponse
    {
        try {
            if ($leaveRequest->status !== 'pending') {
                 return response()->json(["message" => "Cette demande a déjà été traitée."], 400);
            }
            
            $leaveRequest->status = 'approved';
            $leaveRequest->save();

            return response()->json([
                'message' => 'Demande de congé approuvée.',
                'request' => $leaveRequest->load('employee')
            ]);
        } catch (Throwable $e) {
            Log::error("Erreur dans AdminLeaveRequestController@approve: " . $e->getMessage());
            return response()->json(["message" => "Erreur interne lors de l'approbation"], 500);
        }
    }

    /**
     * Rejette une demande de congé.
     */
    public function reject(LeaveRequest $leaveRequest): JsonResponse
    {
        try {
            if ($leaveRequest->status !== 'pending') {
                 return response()->json(["message" => "Cette demande a déjà été traitée."], 400);
            }
            
            $leaveRequest->status = 'rejected';
            $leaveRequest->save();
            
            return response()->json([
                'message' => 'Demande de congé rejetée.',
                'request' => $leaveRequest->load('employee')
            ]);
        } catch (Throwable $e) {
            Log::error("Erreur dans AdminLeaveRequestController@reject: " . $e->getMessage());
            return response()->json(["message" => "Erreur interne lors du rejet"], 500);
        }
    }

    /**
     * Supprime définitivement une demande de congé (Action Admin).
     */
    public function destroy(LeaveRequest $leaveRequest): JsonResponse
    {
        try {
            // Vous pouvez ajouter ici une vérification de statut si vous ne voulez
            // pas que les demandes "pending" soient supprimées, mais généralement
            // l'administrateur a le droit de supprimer n'importe quelle demande.

            $leaveRequest->delete();

            // 204 No Content est la réponse standard pour une suppression réussie
            return response()->json(null, 204);

        } catch (Throwable $e) {
            Log::error("Erreur dans AdminLeaveRequestController@destroy: " . $e->getMessage());
            return response()->json(["message" => "Échec de la suppression de la demande."], 500);
        }
    }
}
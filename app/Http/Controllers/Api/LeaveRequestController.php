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

    public function myLeaveRequests()
    {
        try {
            $employee = Auth::user()->employee;
            if (!$employee) return response()->json(["message" => "Profil employé introuvable."], 404);
            return response()->json($employee->leaveRequests);
        } catch (Throwable $e) {
            Log::error("Erreur dans myLeaveRequests(): " . $e->getMessage());
            return response()->json(["message" => "Erreur interne"], 500);
        }
    }
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

    public function myLeaves()
    {
        try {
            $employee = Auth::user()->employee;
            if (!$employee) {
                return response()->json(["message" => "Profil employé introuvable."], 404);
            }

            $leaves = LeaveRequest::where('employee_id', $employee->id)
                ->latest()
                ->get();

            return response()->json($leaves);
        } catch (Throwable $e) {
            Log::error("Erreur dans myLeaves(): " . $e->getMessage());
            return response()->json(["message" => "Erreur interne"], 500);
        }
    }

    /**
     * Liste pour l'Admin et le Manager (Toutes les demandes du département ou globales)
     */
   public function indexAdmin(): JsonResponse
{
    try {
        // 1. Définir l'utilisateur connecté d'abord
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json(["message" => "Non authentifié"], 401);
        }

        // 2. Initialiser la requête avec la relation employé et son département
        $query = LeaveRequest::with(['employee.department'])->latest();

        // 3. Appliquer le filtre si l'utilisateur n'est PAS un admin (donc c'est un manager)
        if ($user->role !== 'admin') {
            // On récupère l'ID du département du manager via son profil employé
            $deptId = $user->employee ? $user->employee->department_id : null;

            if (!$deptId) {
                return response()->json(["message" => "Département manager introuvable"], 403);
            }

            // Filtrer les demandes : l'employé doit appartenir au même département
            $query->whereHas('employee', function($q) use ($deptId) {
                $q->where('department_id', $deptId);
            });
        }

        // 4. Exécuter la requête
        $leaves = $query->get();

        return response()->json($leaves);

    } catch (\Throwable $e) {
        \Log::error("Erreur indexAdmin: " . $e->getMessage());
        return response()->json([
            "message" => "Erreur lors du chargement des demandes.",
            "error" => $e->getMessage()
        ], 500);
    }
}

    public function approve(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $leaveRequest->update([
            'status' => 'approved',
            'admin_comment' => $request->input('admin_comment')
        ]);
        return response()->json(['message' => 'Approuvée', 'request' => $leaveRequest]);
    }

    public function reject(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $leaveRequest->update([
            'status' => 'rejected',
            'admin_comment' => $request->input('admin_comment')
        ]);
        return response()->json(['message' => 'Rejetée', 'request' => $leaveRequest]);
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

    public function destroy($id): JsonResponse
{
    try {
        $leaveRequest = LeaveRequest::findOrFail($id);
        $leaveRequest->delete();
        return response()->json(null, 204);
    } catch (Throwable $e) {
        Log::error("Erreur suppression: " . $e->getMessage());
        return response()->json(["message" => "Erreur lors de la suppression"], 500);
    }
}
}

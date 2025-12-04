<?php

namespace App\Http\Controllers\Api;

use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class LeaveRequestController extends Controller
{
    /**
     * Lister toutes les demandes de congé (avec pagination, recherche et filtres)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = LeaveRequest::with('employee:id,first_name,last_name');
            
            if ($search = $request->get('search')) {
                $query->where(function($q) use ($search) {
                    $q->where('type', 'like', "%{$search}%")
                      ->orWhere('status', 'like', "%{$search}%")
                      ->orWhereHas('employee', function($employeeQuery) use ($search) {
                          $employeeQuery->where('first_name', 'like', "%{$search}%")
                                        ->orWhere('last_name', 'like', "%{$search}%");
                      });
                });
            }
            
            if ($status = $request->get('status')) {
                $query->where('status', $status);
            }
            
            if ($employeeId = $request->get('employee_id')) {
                $query->where('employee_id', $employeeId);
            }
            
            $requests = $query->latest()->paginate(10);

            return response()->json($requests, 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des demandes.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Créer une nouvelle demande de congé
     */
    public function store(Request $request): JsonResponse
    {
        try {
            Log::info('📥 Données brutes reçues:', $request->all());
            
            $validated = $request->validate([
                'employee_id' => 'required|integer|exists:employees,id',
                'type' => 'required|string|in:vacances,maladie,impayé,autres',
                'start_date' => 'required|date', // Accepte plusieurs formats automatiquement
                'end_date' => 'required|date|after_or_equal:start_date',
                'message' => 'nullable|string|max:1000',
            ]);

            Log::info('✅ Validation réussie:', $validated);

            // Normaliser les dates au format Y-m-d pour la base de données
            try {
                $validated['start_date'] = Carbon::parse($validated['start_date'])->format('Y-m-d');
                $validated['end_date'] = Carbon::parse($validated['end_date'])->format('Y-m-d');
            } catch (\Exception $e) {
                Log::error('❌ Erreur parsing date:', ['error' => $e->getMessage()]);
                return response()->json([
                    'message' => 'Format de date invalide.',
                    'errors' => [
                        'start_date' => ['Le format de date doit être AAAA-MM-JJ ou JJ-MM-AAAA']
                    ]
                ], 422);
            }

            // Validation métier: la date de début doit être aujourd'hui ou dans le futur
            if (Carbon::parse($validated['start_date'])->lt(Carbon::today())) {
                return response()->json([
                    'message' => 'La date de début doit être aujourd\'hui ou dans le futur.',
                    'errors' => [
                        'start_date' => ['La date de début ne peut pas être dans le passé.']
                    ]
                ], 422);
            }
            
            $validated['employee_id'] = (int) $validated['employee_id']; 
            $validated['status'] = 'pending';

            Log::info('📝 Création avec les données normalisées:', $validated);

            $leaveRequest = LeaveRequest::create($validated);
            $leaveRequest->load('employee');

            Log::info('✅ Demande créée avec succès:', ['id' => $leaveRequest->id]);

            return response()->json([
                'message' => 'Demande de congé créée avec succès.',
                'data' => $leaveRequest
            ], 201);
            
        } catch (ValidationException $e) {
            Log::error('❌ Validation échouée:', $e->errors());
            
            return response()->json([
                'message' => 'Les données fournies sont invalides.',
                'errors' => $e->errors(),
            ], 422);
            
        } catch (\Throwable $th) {
            Log::error('❌ Erreur lors de la création:', [
                'message' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'trace' => $th->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de la création de la demande.',
                'error' => $th->getMessage(),
                'debug' => config('app.debug') ? [
                    'file' => $th->getFile(),
                    'line' => $th->getLine()
                ] : null
            ], 500);
        }
    }

    /**
     * Afficher une demande spécifique
     */
    public function show(LeaveRequest $leaveRequest): JsonResponse
    {
        try {
            $leaveRequest->load('employee');
            return response()->json($leaveRequest, 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération de la demande.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour une demande de congé
     */
    public function update(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        try {
            Log::info('📥 Mise à jour - données reçues:', $request->all());
            
            $validated = $request->validate([
                'employee_id' => 'sometimes|required|integer|exists:employees,id',
                'type' => 'sometimes|required|string|in:vacances,maladie,impayé,autres',
                'start_date' => 'sometimes|required|date',
                'end_date' => 'sometimes|required|date|after_or_equal:start_date',
                'status' => 'sometimes|required|string|in:pending,approved,rejected',
                'message' => 'nullable|string|max:1000',
            ]);

            // Normaliser les dates si présentes
            if (isset($validated['start_date'])) {
                $validated['start_date'] = Carbon::parse($validated['start_date'])->format('Y-m-d');
            }
            if (isset($validated['end_date'])) {
                $validated['end_date'] = Carbon::parse($validated['end_date'])->format('Y-m-d');
            }

            if (isset($validated['employee_id'])) {
                $validated['employee_id'] = (int) $validated['employee_id'];
            }
            
            $leaveRequest->update($validated);
            $leaveRequest->load('employee');

            return response()->json([
                'message' => 'Demande mise à jour avec succès.',
                'data' => $leaveRequest
            ], 200);
            
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Les données fournies sont invalides.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour de la demande.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer une demande
     */
    public function destroy(LeaveRequest $leaveRequest): JsonResponse
    {
        try {
            if ($leaveRequest->status === 'approved') {
                return response()->json([
                    'message' => 'Impossible de supprimer une demande approuvée.'
                ], 403);
            }
            
            $leaveRequest->delete();
            
            return response()->json([
                'message' => 'Demande supprimée avec succès.'
            ], 200);
            
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la suppression de la demande.',
                'error' => $th->getMessage()
            ], 500);
        }
    }
    
    /**
     * Statistiques sur les congés
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $employeeId = $request->get('employee_id');
            
            $query = LeaveRequest::query();
            
            if ($employeeId) {
                $query->where('employee_id', $employeeId);
            }
            
            $stats = [
                'total' => $query->count(),
                'pending' => (clone $query)->where('status', 'pending')->count(),
                'approved' => (clone $query)->where('status', 'approved')->count(),
                'rejected' => (clone $query)->where('status', 'rejected')->count(),
                'by_type' => $query->selectRaw('type, COUNT(*) as count')
                                       ->groupBy('type')
                                       ->get()
            ];
            
            return response()->json($stats, 200);
            
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors du calcul des statistiques.',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
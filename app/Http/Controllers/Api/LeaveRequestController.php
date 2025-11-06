<?php

namespace App\Http\Controllers\Api;

use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class LeaveRequestController extends Controller
{
    /**
     * Lister toutes les demandes de congé (avec pagination)
     */
    public function index(): JsonResponse
    {
        try {
            $requests = LeaveRequest::with('employee:id,first_name,last_name')
                ->latest()
                ->paginate(10);

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
            $validated = $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'type' => 'required|string|in:vacances,impayé,autres,maladie',
                'start_date' => 'required|date|after_or_equal:today', 
                'end_date' => 'required|date|after_or_equal:start_date', 
                'message' => 'nullable|string',
            ]);

            $validated['status'] = 'pending';

            $leaveRequest = LeaveRequest::create($validated);

            $leaveRequest->load('employee');

            return response()->json($leaveRequest, 201);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la création de la demande.',
                'error' => $th->getMessage()
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
     * Mettre à jour le status ou le message d’une demande
     */
    public function update(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        try {
            $validated = $request->validate([
                'status' => 'sometimes|required|string|in:pending,approved,rejected', 
                'message' => 'nullable|string',
            ]);

            $leaveRequest->update($validated);

            return response()->json($leaveRequest, 200);
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
            $leaveRequest->delete();
            return response()->json(null, 204);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la suppression de la demande.',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}

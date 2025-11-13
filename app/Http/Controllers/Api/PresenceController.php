<?php

namespace App\Http\Controllers\Api;

use App\Models\Presence;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PresenceController extends Controller
{
    /**
     * Afficher la liste des présences
     */
    public function index(): JsonResponse
    {
        try {
            $presences = Presence::with('employee:id,first_name,last_name')
                ->latest()
                ->paginate(20);

            return response()->json($presences);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des présences.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Enregistrer une nouvelle présence
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'date' => 'nullable|date',
                'check_in' => 'nullable|date',
                'check_out' => 'nullable|date|after_or_equal:check_in',
                'total_hours' => 'nullable|numeric',
            ]);

            // Définit automatiquement la date et l’heure d’entrée si absentes
            $validated['date'] = $validated['date'] ?? date('Y-m-d');
            $validated['check_in'] = $validated['check_in'] ?? date('Y-m-d H:i:s');

            // Vérifie s’il existe déjà un check-in actif
            $existing = Presence::where('employee_id', $validated['employee_id'])
                ->whereNull('check_out')
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => 'Un check-in est déjà actif pour cet employé.'
                ], 409);
            }

            // Si check_out est présent, calcule automatiquement total_hours
            if (!empty($validated['check_in']) && !empty($validated['check_out'])) {
                $start = strtotime($validated['check_in']);
                $end = strtotime($validated['check_out']);
                $validated['total_hours'] = round(($end - $start) / 3600, 2);
            }

            $presence = Presence::create($validated);

            return response()->json($presence, 201);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de l’enregistrement de la présence.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher une présence spécifique
     */
    public function show(Presence $presence): JsonResponse
    {
        try {
            $presence->load('employee');
            return response()->json($presence);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération de la présence.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour une présence (ajout du check_out)
     */
    public function update(Request $request, Presence $presence): JsonResponse
    {
        try {
            $validated = $request->validate([
                'check_out' => 'required|date|after_or_equal:check_in',
            ]);

            $presence->check_out = $validated['check_out'];

            // Calcul de la durée totale
            $checkInTimestamp = strtotime($presence->check_in);
            $checkOutTimestamp = strtotime($validated['check_out']);
            $durationInHours = ($checkOutTimestamp - $checkInTimestamp) / 3600;

            $presence->total_hours = round($durationInHours, 2);
            $presence->save();

            return response()->json($presence);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour de la présence.',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer une présence
     */
    public function destroy(Presence $presence): JsonResponse
    {
        try {
            $presence->delete();
            return response()->json(null, 204);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la suppression de la présence.',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Models\Presence;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PresenceController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $presences = Presence::with('employee:id,first_name,last_name')->latest()->paginate(20);
            return response()->json($presences);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Erreur lors de la récupération des présences.'], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'check_in' => 'nullable|date', 
            ]);

            $validated['check_in'] = $validated['check_in'] ?? now();

            $existing = Presence::where('employee_id', $validated['employee_id'])
                ->whereNull('check_out')
                ->first();

            if ($existing) {
                return response()->json(['message' => 'Un check-in est déjà actif pour cet employé.'], 409);
            }

            $presence = Presence::create($validated);

            return response()->json($presence, 201);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Erreur lors de l’enregistrement de la présence.'], 500);
        }
    }

    public function show(Presence $presence): JsonResponse
    {
        try {
            $presence->load('employee');
            return response()->json($presence);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Erreur lors de la récupération de la présence.'], 500);
        }
    }

    public function update(Request $request, Presence $presence): JsonResponse
    {
        try {
            $validated = $request->validate([
                'check_out' => 'required|date|after_or_equal:check_in', 
            ]);

            $presence->check_out = $validated['check_out'];

            $duration = $presence->check_in->diffInMinutes($presence->check_out) / 60;
            $presence->total_hours = round($duration, 2);

            $presence->save();

            return response()->json($presence);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Erreur lors de la mise à jour de la présence.'], 500);
        }
    }

    public function destroy(Presence $presence): JsonResponse
    {
        try {
            $presence->delete();
            return response()->json(null, 204);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Erreur lors de la suppression de la présence.'], 500);
        }
    }
}

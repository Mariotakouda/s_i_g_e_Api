<?php

namespace App\Http\Controllers\Api;

use App\Models\Presence;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PresenceController extends Controller
{
    /**
     * Statistiques de présence
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $query = Presence::query();

            // Si c'est un employé, il ne voit que ses propres stats
            // Si c'est un admin, il voit tout par défaut (ou vous pouvez ajouter un filtre employee_id)
            if ($request->user()->role === 'employee') {
                $query->where('employee_id', $request->user()->employee->id);
            }

            $presences = $query->latest()->get();
            $totalHours = $presences->sum('total_hours');

            return response()->json([
                'total_hours' => round($totalHours, 2),
                'days_present' => $presences->count(),
                'history' => $presences
            ]);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    /**
     * Liste globale (Vue Admin)
     */
    public function index(Request $request): JsonResponse
{
    try {
        $query = Presence::with('employee:id,first_name,last_name');

        // Filtrer par date spécifique (ex: 2025-12-25)
        if ($request->has('date') && $request->date != '') {
            $query->whereDate('date', $request->date);
        }

        // Filtrer par mois (ex: 12)
        if ($request->has('month') && $request->month != '') {
            $query->whereMonth('date', $request->month);
        }

        $presences = $query->latest('date')->paginate(20);

        return response()->json($presences);
    } catch (\Throwable $th) {
        return response()->json(['message' => 'Erreur de filtrage'], 500);
    }
}

    /**
     * Pointage d'arrivée (Check-in)
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user->employee) {
                return response()->json(['message' => 'Profil employé non trouvé'], 404);
            }

            $employeeId = $user->employee->id;
            $today = date('Y-m-d');

            $existing = Presence::where('employee_id', $employeeId)
                ->whereDate('date', $today)
                ->whereNull('check_out')
                ->first();

            if ($existing) {
                return response()->json(['message' => 'Un pointage est déjà en cours.'], 409);
            }

            $presence = Presence::create([
                'employee_id' => $employeeId,
                'date' => $today,
                'check_in' => now(),
                'status' => 'present'
            ]);

            return response()->json($presence, 201);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    /**
     * Pointage de sortie (Check-out)
     */
    public function update(Request $request, Presence $presence): JsonResponse
    {
        try {
            // Validation de la date de sortie (optionnel si géré auto par le serveur)
            $checkOutTime = $request->check_out ? $request->check_out : now();

            $presence->check_out = $checkOutTime;

            // Calcul des heures
            $checkIn = \Carbon\Carbon::parse($presence->check_in);
            $checkOut = \Carbon\Carbon::parse($checkOutTime);
            $presence->total_hours = round($checkIn->diffInMinutes($checkOut) / 60, 2);
            
            $presence->save();

            return response()->json($presence);
        } catch (\Throwable $th) {
            Log::error("Erreur update presence: " . $th->getMessage());
            return response()->json(['message' => 'Erreur lors de la mise à jour'], 500);
        }
    }

    public function destroy(Presence $presence): JsonResponse
    {
        $presence->delete();
        return response()->json(null, 204);
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Models\Presence;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

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

    public function myPresences()
    {
        try {
            $user = Auth::user();
            Log::info("🔍 myPresences - User:", ['id' => $user->id, 'email' => $user->email]);

            $employee = $user->employee;

            if (!$employee) {
                Log::warning("⚠️ Aucun profil employé pour user " . $user->id);
                return response()->json(["message" => "Profil employé introuvable."], 404);
            }

            Log::info("👤 Employee trouvé:", ['id' => $employee->id, 'name' => $employee->first_name]);

            $presences = Presence::where('employee_id', $employee->id)
                ->latest()
                ->get();

            Log::info("📋 Présences trouvées:", ['count' => $presences->count()]);

            return response()->json($presences);
        } catch (Throwable $e) {
            Log::error("❌ Erreur dans myPresences(): " . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(["message" => "Erreur interne", "error" => $e->getMessage()], 500);
        }
    }


    /**
     * Liste globale (Vue Admin)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // On charge la relation employee pour avoir les noms dans le dashboard
            $query = Presence::with('employee:id,first_name,last_name');

            // Filtre par date (important pour le dashboard admin)
            if ($request->filled('date')) {
                $query->whereDate('date', $request->date);
            }

            // Filtre par mois
            if ($request->filled('month')) {
                $query->whereMonth('date', $request->month);
            }

            $presences = $query->latest()->paginate(20);

            return response()->json($presences);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Erreur lors de la récupération'], 500);
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

    public function export()
{
    try {
        // 1. Récupération des données avec la relation employee
        $presences = Presence::with('employee:id,first_name,last_name')
            ->latest()
            ->get();

        if ($presences->isEmpty()) {
            return response()->json(['message' => 'Aucune donnée à exporter'], 404);
        }

        $filename = "export_presences_" . date('d-m-Y') . ".csv";

        // 2. Préparation du flux de sortie
        $callback = function() use($presences) {
            $file = fopen('php://output', 'w');
            
            // Étape capitale : Le BOM UTF-8 (permet à WPS de lire les accents et de voir le fichier)
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // 3. Entêtes du tableau (Utilisation du point-virgule pour WPS)
            fputcsv($file, ['ID', 'Employé', 'Date', 'Arrivée', 'Sortie', 'Heures Totales'], ';');

            // 4. Remplissage des lignes
            foreach ($presences as $presence) {
                fputcsv($file, [
                    $presence->id,
                    ($presence->employee->first_name ?? '') . ' ' . ($presence->employee->last_name ?? ''),
                    $presence->date,
                    // Format court HH:mm pour éviter les colonnes trop larges (###)
                    $presence->check_in ? date('H:i', strtotime($presence->check_in)) : '--:--',
                    $presence->check_out ? date('H:i', strtotime($presence->check_out)) : '--:--',
                    // Remplacement du point par la virgule pour que WPS reconnaisse un nombre
                    str_replace('.', ',', $presence->total_hours ?? '0')
                ], ';');
            }
            
            fclose($file);
        };

        // 5. Envoi de la réponse avec les bons headers
        return response()->stream($callback, 200, [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ]);

    } catch (\Throwable $th) {
        return response()->json(['error' => 'Erreur lors de l\'export : ' . $th->getMessage()], 500);
    }
}
}

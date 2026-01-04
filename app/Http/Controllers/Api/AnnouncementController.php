<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnnouncementController extends Controller
{
    /**
     * ✅ Liste des annonces (filtrées selon le rôle)
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $search = $request->query('search', '');

            $query = Announcement::with(['creator', 'department'])
                ->orderBy('created_at', 'desc');

            // ✅ Filtrage intelligent selon le rôle
            if ($user->role !== 'admin') {
                $deptId = $user->employee->department_id ?? null;

                $query->where(function ($q) use ($deptId) {
                    // Tout le monde voit les annonces générales
                    $q->where('is_general', true);

                    // On voit aussi les annonces de son propre département (si on en a un)
                    if ($deptId) {
                        $q->orWhere('department_id', $deptId);
                    }
                });
            }
            // Si c'est un Admin, il ne rentre pas dans le 'if', donc il voit TOUT.

            // ✅ Gestion de la recherche
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%");
                });
            }

            $announcements = $query->paginate(15);
            return response()->json($announcements, 200);
        } catch (Throwable $e) {
            Log::error("Erreur index announcements: " . $e->getMessage());
            return response()->json(["message" => "Erreur interne"], 500);
        }
    }

    /**
     * ✅ NOUVELLE MÉTHODE : Annonces pour l'employé connecté (sans pagination)
     */
    public function myAnnouncements()
    {
        try {
            $user = Auth::user();

            // On récupère l'ID du département de l'employé
            $deptId = $user->employee->department_id ?? null;

            $announcements = Announcement::with(['creator', 'department'])
                ->where(function ($q) use ($deptId) {
                    // 1. Toujours inclure les annonces générales
                    $q->where('is_general', true);

                    // 2. Inclure les annonces du département si l'employé en a un
                    if ($deptId) {
                        $q->orWhere('department_id', $deptId);
                    }
                })
                ->orderBy('created_at', 'desc')
                ->limit(5) // On limite pour le Dashboard
                ->get();

            // On retourne la structure attendue par le Dashboard
            return response()->json([
                'status' => 'success',
                'data' => $announcements
            ], 200);
        } catch (\Throwable $e) {
            \Log::error("Erreur Dashboard Annonces: " . $e->getMessage());
            return response()->json(["message" => "Erreur de chargement"], 500);
        }
    }

    /**
     * ✅ Créer une annonce
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'department_id' => 'nullable|exists:departments,id',
            'is_general' => 'boolean'
        ]);

        try {
            $user = Auth::user();

            // Vérifier les permissions pour les managers
            if ($user->role === 'manager' || $this->isEmployeeManager($user)) {
                $managerDeptId = $user->employee->department_id ?? null;

                // Si c'est une annonce de département, vérifier que c'est le sien
                if (isset($validated['department_id']) && $validated['department_id'] !== null) {
                    if ($validated['department_id'] !== $managerDeptId) {
                        return response()->json([
                            "message" => "Vous ne pouvez créer des annonces que pour votre département."
                        ], 403);
                    }
                }
            }

            // Si department_id est null, forcer is_general à true
            if (!isset($validated['department_id']) || $validated['department_id'] === null) {
                $validated['is_general'] = true;
            } else {
                $validated['is_general'] = false;
            }

            $announcement = Announcement::create([
                'title' => $validated['title'],
                'message' => $validated['message'],
                'department_id' => $validated['department_id'] ?? null,
                'is_general' => $validated['is_general'],
                'creator_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Annonce créée avec succès',
                'data' => $announcement->load(['creator', 'department'])
            ], 201);
        } catch (Throwable $e) {
            Log::error("Erreur création annonce: " . $e->getMessage());
            return response()->json([
                "message" => "Erreur lors de la création",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ Afficher une annonce
     */
    public function show(Announcement $announcement)
    {
        try {
            $user = Auth::user();

            // Vérifier l'accès selon le rôle
            if ($user->role === 'employee' || $user->role === 'manager') {
                $deptId = $user->employee->department_id ?? null;

                // Peut voir si c'est général OU si c'est son département
                $canView = $announcement->is_general ||
                    $announcement->department_id === $deptId;

                if (!$canView) {
                    return response()->json([
                        "message" => "Accès refusé à cette annonce."
                    ], 403);
                }
            }

            $announcement->load(['creator', 'department']);
            return response()->json(['data' => $announcement], 200);
        } catch (Throwable $e) {
            Log::error("Erreur show announcement: " . $e->getMessage());
            return response()->json(["message" => "Erreur interne"], 500);
        }
    }

    /**
     * ✅ Modifier une annonce
     */
    public function update(Request $request, Announcement $announcement)
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'message' => 'sometimes|string',
            'department_id' => 'nullable|exists:departments,id',
            'is_general' => 'boolean'
        ]);

        try {
            $user = Auth::user();

            // Vérifier que l'utilisateur peut modifier cette annonce
            if ($user->role !== 'admin') {
                // Seul le créateur ou un admin peut modifier
                if ($announcement->creator_id !== $user->id) {
                    return response()->json([
                        "message" => "Vous ne pouvez modifier que vos propres annonces."
                    ], 403);
                }

                // Manager : vérifier le département
                if ($user->role === 'manager' || $this->isEmployeeManager($user)) {
                    $managerDeptId = $user->employee->department_id ?? null;

                    if (
                        isset($validated['department_id']) &&
                        $validated['department_id'] !== null &&
                        $validated['department_id'] !== $managerDeptId
                    ) {
                        return response()->json([
                            "message" => "Vous ne pouvez créer des annonces que pour votre département."
                        ], 403);
                    }
                }
            }

            $announcement->update($validated);

            return response()->json([
                'message' => 'Annonce mise à jour',
                'data' => $announcement->load(['creator', 'department'])
            ], 200);
        } catch (Throwable $e) {
            Log::error("Erreur update announcement: " . $e->getMessage());
            return response()->json(["message" => "Erreur interne"], 500);
        }
    }

    /**
     * ✅ Supprimer une annonce
     */
    public function destroy(Announcement $announcement)
    {
        try {
            $user = Auth::user();

            // Seul l'admin ou le créateur peut supprimer
            if ($user->role !== 'admin' && $announcement->creator_id !== $user->id) {
                return response()->json([
                    "message" => "Vous ne pouvez supprimer que vos propres annonces."
                ], 403);
            }

            $announcement->delete();

            return response()->json(["message" => "Annonce supprimée"], 204);
        } catch (Throwable $e) {
            Log::error("Erreur suppression announcement: " . $e->getMessage());
            return response()->json(["message" => "Erreur suppression"], 500);
        }
    }

    /**
     * ✅ Vérifie si un employé a le rôle de manager
     */
    private function isEmployeeManager($user): bool
    {
        if (!$user->employee) return false;

        return $user->role === 'manager' ||
            $user->employee->roles()->where('name', 'manager')->exists() ||
            \App\Models\Manager::where('employee_id', $user->employee->id)->exists();
    }
}

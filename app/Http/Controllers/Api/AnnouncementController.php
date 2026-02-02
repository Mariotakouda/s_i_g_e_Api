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
     * ✅ Annonces pour l'employé connecté (sans pagination)
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
     * 🔥 CORRECTION : Créer une annonce (ADMIN et MANAGER uniquement)
     */
    public function store(Request $request)
    {
        try {
            $user = Auth::user();

            // 🔥 VÉRIFICATION : Seuls admin et manager peuvent créer
            if (!$this->canManageAnnouncements($user)) {
                Log::warning("❌ Tentative de création par un employé non autorisé", [
                    'user_id' => $user->id,
                    'user_role' => $user->role
                ]);
                return response()->json([
                    "message" => "Vous n'avez pas les permissions pour créer des annonces."
                ], 403);
            }

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'message' => 'required|string',
                'department_id' => 'nullable|exists:departments,id',
                'is_general' => 'boolean'
            ]);

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
                'user_id' => $user->id,
            ]);

            Log::info("✅ Annonce créée", [
                'announcement_id' => $announcement->id,
                'created_by' => $user->id,
                'user_role' => $user->role
            ]);

            return response()->json([
                'message' => 'Annonce créée avec succès',
                'data' => $announcement->load(['creator', 'department'])
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                "message" => "Données invalides",
                "errors" => $e->errors()
            ], 422);
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
     * 🔥 CORRECTION : Modifier une annonce (ADMIN et MANAGER uniquement)
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

            Log::info("🔍 Tentative de modification d'annonce", [
                'announcement_id' => $announcement->id,
                'user_id' => $user->id,
                'user_role' => $user->role,
                'announcement_creator_id' => $announcement->user_id,
                'announcement_department_id' => $announcement->department_id
            ]);

            // 🔥 VÉRIFICATION : Seuls admin et manager peuvent modifier
            if (!$this->canManageAnnouncements($user)) {
                Log::warning("❌ Tentative de modification par un employé non autorisé", [
                    'user_id' => $user->id,
                    'user_role' => $user->role
                ]);
                return response()->json([
                    "message" => "Vous n'avez pas les permissions pour modifier des annonces."
                ], 403);
            }

            // 🔥 CAS 1: ADMIN - Peut tout modifier
            if ($user->role === 'admin') {
                Log::info("✅ Admin - Modification autorisée");
                $announcement->update($validated);

                return response()->json([
                    'message' => 'Annonce mise à jour',
                    'data' => $announcement->load(['creator', 'department'])
                ], 200);
            }

            // 🔥 CAS 2: MANAGER - Peut modifier les annonces de son département
            if ($user->role === 'manager' || $this->isEmployeeManager($user)) {
                $managerDeptId = $user->employee->department_id ?? null;

                Log::info("🔍 Vérification Manager", [
                    'manager_dept_id' => $managerDeptId,
                    'announcement_dept_id' => $announcement->department_id,
                    'is_creator' => $announcement->user_id === $user->id
                ]);

                // Le manager peut modifier si:
                // 1. C'est lui qui a créé l'annonce OU
                // 2. L'annonce concerne son département
                $canModify = ($announcement->user_id === $user->id) || 
                            ($announcement->department_id === $managerDeptId);

                if (!$canModify) {
                    Log::warning("❌ Manager - Accès refusé", [
                        'reason' => 'Not creator and not his department'
                    ]);
                    return response()->json([
                        "message" => "Vous ne pouvez modifier que vos annonces ou celles de votre département."
                    ], 403);
                }

                // Vérifier que si on change le département, c'est toujours le sien
                if (isset($validated['department_id']) && 
                    $validated['department_id'] !== null && 
                    $validated['department_id'] !== $managerDeptId) {
                    Log::warning("❌ Manager - Tentative de changer vers un autre département");
                    return response()->json([
                        "message" => "Vous ne pouvez assigner l'annonce qu'à votre département."
                    ], 403);
                }

                Log::info("✅ Manager - Modification autorisée");
                $announcement->update($validated);

                return response()->json([
                    'message' => 'Annonce mise à jour',
                    'data' => $announcement->load(['creator', 'department'])
                ], 200);
            }

            // 🔥 Si on arrive ici, c'est un problème (ne devrait pas arriver)
            Log::error("❌ Cas non géré dans update()", [
                'user_role' => $user->role
            ]);
            return response()->json([
                "message" => "Erreur de permissions."
            ], 403);

        } catch (Throwable $e) {
            Log::error("Erreur update announcement: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(["message" => "Erreur interne"], 500);
        }
    }

    /**
     * 🔥 CORRECTION : Supprimer une annonce (ADMIN et MANAGER uniquement)
     */
    public function destroy(Announcement $announcement)
    {
        try {
            $user = Auth::user();

            Log::info("🔍 Tentative de suppression d'annonce", [
                'announcement_id' => $announcement->id,
                'user_id' => $user->id,
                'user_role' => $user->role,
                'announcement_creator_id' => $announcement->user_id
            ]);

            // 🔥 VÉRIFICATION : Seuls admin et manager peuvent supprimer
            if (!$this->canManageAnnouncements($user)) {
                Log::warning("❌ Tentative de suppression par un employé non autorisé", [
                    'user_id' => $user->id,
                    'user_role' => $user->role
                ]);
                return response()->json([
                    "message" => "Vous n'avez pas les permissions pour supprimer des annonces."
                ], 403);
            }

            // Admin peut tout supprimer
            if ($user->role === 'admin') {
                Log::info("✅ Admin - Suppression autorisée");
                $announcement->delete();
                return response()->json(["message" => "Annonce supprimée"], 204);
            }

            // Manager peut supprimer les annonces de son département
            if ($user->role === 'manager' || $this->isEmployeeManager($user)) {
                $managerDeptId = $user->employee->department_id ?? null;
                
                $canDelete = ($announcement->user_id === $user->id) || 
                            ($announcement->department_id === $managerDeptId);

                if (!$canDelete) {
                    Log::warning("❌ Manager - Suppression refusée");
                    return response()->json([
                        "message" => "Vous ne pouvez supprimer que vos annonces ou celles de votre département."
                    ], 403);
                }

                Log::info("✅ Manager - Suppression autorisée");
                $announcement->delete();
                return response()->json(["message" => "Annonce supprimée"], 204);
            }

            // Ne devrait jamais arriver ici
            return response()->json([
                "message" => "Erreur de permissions."
            ], 403);

        } catch (Throwable $e) {
            Log::error("Erreur suppression announcement: " . $e->getMessage());
            return response()->json(["message" => "Erreur suppression"], 500);
        }
    }

    /**
     * 🔥 NOUVELLE MÉTHODE : Vérifie si l'utilisateur peut gérer les annonces
     */
    private function canManageAnnouncements($user): bool
    {
        // Admin peut toujours gérer
        if ($user->role === 'admin') {
            return true;
        }

        // Manager peut gérer
        if ($user->role === 'manager') {
            return true;
        }

        // Vérifier si l'employé a un rôle de manager dans la table pivot
        if ($this->isEmployeeManager($user)) {
            return true;
        }

        // Employé simple ne peut pas gérer
        return false;
    }

    /**
     * ✅ Vérifie si un employé a le rôle de manager
     */
    private function isEmployeeManager($user): bool
    {
        if (!$user->employee) return false;

        return $user->employee->roles()->where('name', 'manager')->exists() ||
            \App\Models\Manager::where('employee_id', $user->employee->id)->exists();
    }
}
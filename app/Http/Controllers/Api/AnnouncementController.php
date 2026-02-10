<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\User;
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
        /** @var User $user */
        $user = Auth::user();
        $search = $request->query('search', '');

        $query = Announcement::with(['creator', 'department', 'employee'])
            ->orderBy('created_at', 'desc');

        // Filtrage par rôle (votre logique actuelle est correcte)
        if ($user->role !== 'admin') {
            $deptId = $user->employee->department_id ?? null;
            $employeeId = $user->employee->id ?? null;

            $query->where(function ($q) use ($deptId, $employeeId) {
                $q->where('is_general', true);
                if ($deptId) $q->orWhere('department_id', $deptId);
                if ($employeeId) $q->orWhere('employee_id', $employeeId);
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $paginated = $query->paginate(15);

        // ✅ ON RETOURNE UNE STRUCTURE QUE REACT COMPREND BIEN
        return response()->json([
            'data' => $paginated->items(),
            'meta' => [
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
            ]
        ], 200);

    } catch (Throwable $e) {
        Log::error("Erreur index announcements: " . $e->getMessage());
        return response()->json(["message" => "Erreur interne"], 500);
    }
}

    /**
     * ✅ Annonces pour l'employé connecté (Dashboard)
     */
    public function myAnnouncements()
    {
        try {
            /** @var User $user */
            $user = Auth::user();
            $deptId = $user->employee->department_id ?? null;
            $employeeId = $user->employee->id ?? null;

            $announcements = Announcement::with(['creator', 'department'])
                ->where(function ($q) use ($deptId, $employeeId) {
                    $q->where('is_general', true);
                    
                    if ($deptId) {
                        $q->orWhere('department_id', $deptId);
                    }

                    if ($employeeId) {
                        $q->orWhere('employee_id', $employeeId);
                    }
                })
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $announcements
            ], 200);
        } catch (Throwable $e) {
            Log::error("Erreur Dashboard Annonces: " . $e->getMessage());
            return response()->json(["message" => "Erreur de chargement"], 500);
        }
    }

    /**
     * ✅ Créer une annonce (ADMIN et MANAGER uniquement)
     */
    public function store(Request $request)
    {
        try {
            /** @var User $user */
            $user = Auth::user();

            if (!$this->canManageAnnouncements($user)) {
                return response()->json(["message" => "Permissions insuffisantes."], 403);
            }

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'message' => 'required|string',
                'department_id' => 'nullable|exists:departments,id',
                'employee_id' => 'nullable|exists:employees,id',
                'is_general' => 'boolean'
            ]);

            // Restriction pour les Managers (uniquement leur département)
            if ($user->role === 'manager' || $this->isEmployeeManager($user)) {
                $managerDeptId = $user->employee->department_id ?? null;
                if (isset($validated['department_id']) && $validated['department_id'] != $managerDeptId) {
                    return response()->json(["message" => "Action limitée à votre département."], 403);
                }
            }

            // Correction de la logique is_general
            // Si pas de département ET pas d'employé spécifique, alors c'est général
            if (empty($validated['department_id']) && empty($validated['employee_id'])) {
                $validated['is_general'] = true;
            } else {
                $validated['is_general'] = false;
            }

            $announcement = Announcement::create([
                'title' => $validated['title'],
                'message' => $validated['message'],
                'department_id' => $validated['department_id'] ?? null,
                'employee_id' => $validated['employee_id'] ?? null,
                'is_general' => $validated['is_general'],
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Annonce créée avec succès',
                'data' => $announcement->load(['creator', 'department', 'employee'])
            ], 201);

        } catch (Throwable $e) {
            Log::error("Erreur création annonce: " . $e->getMessage());
            return response()->json(["message" => "Erreur lors de la création"], 500);
        }
    }

    /**
     * ✅ Afficher une annonce
     */
    public function show(Announcement $announcement)
    {
        try {
            /** @var User $user */
            $user = Auth::user();

            if ($user->role !== 'admin') {
                $deptId = $user->employee->department_id ?? null;
                $empId = $user->employee->id ?? null;

                $canView = $announcement->is_general ||
                           $announcement->department_id === $deptId ||
                           $announcement->employee_id === $empId;

                if (!$canView) {
                    return response()->json(["message" => "Accès refusé."], 403);
                }
            }

            $announcement->load(['creator', 'department', 'employee']);
            return response()->json(['data' => $announcement], 200);
        } catch (Throwable $e) {
            return response()->json(["message" => "Erreur interne"], 500);
        }
    }

    /**
     * ✅ Modifier une annonce
     */
    public function update(Request $request, Announcement $announcement)
    {
        try {
            /** @var User $user */
            $user = Auth::user();

            if (!$this->canManageAnnouncements($user)) {
                return response()->json(["message" => "Permissions insuffisantes."], 403);
            }

            // Si manager, vérifier s'il est l'auteur ou si c'est son département
            if ($user->role !== 'admin') {
                $managerDeptId = $user->employee->department_id ?? null;
                $canModify = ($announcement->user_id === $user->id) || ($announcement->department_id === $managerDeptId);
                
                if (!$canModify) {
                    return response()->json(["message" => "Vous ne pouvez modifier que vos annonces."], 403);
                }
            }

            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'message' => 'sometimes|string',
                'department_id' => 'nullable|exists:departments,id',
                'employee_id' => 'nullable|exists:employees,id',
                'is_general' => 'boolean'
            ]);

            $announcement->update($validated);

            return response()->json([
                'message' => 'Annonce mise à jour',
                'data' => $announcement->load(['creator', 'department', 'employee'])
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
            /** @var User $user */
            $user = Auth::user();

            if (!$this->canManageAnnouncements($user)) {
                return response()->json(["message" => "Permissions insuffisantes."], 403);
            }

            if ($user->role !== 'admin') {
                $managerDeptId = $user->employee->department_id ?? null;
                if ($announcement->user_id !== $user->id && $announcement->department_id !== $managerDeptId) {
                    return response()->json(["message" => "Action non autorisée."], 403);
                }
            }

            $announcement->delete();
            return response()->json(["message" => "Annonce supprimée"], 204);
        } catch (Throwable $e) {
            return response()->json(["message" => "Erreur suppression"], 500);
        }
    }

    /**
     * Helpers de permissions
     */
    private function canManageAnnouncements($user): bool
    {
        return in_array($user->role, ['admin', 'manager']) || $this->isEmployeeManager($user);
    }

    private function isEmployeeManager($user): bool
    {
        if (!$user->employee) return false;
        return $user->employee->roles()->where('name', 'manager')->exists() ||
               \App\Models\Manager::where('employee_id', $user->employee->id)->exists();
    }
}
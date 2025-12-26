<?php

namespace App\Http\Controllers\Api;

use App\Models\Announcement;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnnouncementController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // ADMIN : Voir toutes les annonces
            if ($user->role === 'admin') {
                $announcements = Announcement::with(['employee:id,first_name,last_name', 'department:id,name', 'creator:id,name'])
                    ->orderBy('created_at', 'desc')
                    ->paginate(10);
                return response()->json($announcements);
            }
            
            // MANAGER : Voir annonces générales + son département + celles qu'il a créées
            if ($user->role === 'employee') {
                $manager = $this->isManager($user);
                
                if ($manager) {
                    $announcements = Announcement::with(['employee:id,first_name,last_name', 'department:id,name', 'creator:id,name'])
                        ->where(function($query) use ($manager, $user) {
                            $query->where('is_general', true)
                                  ->orWhere('department_id', $manager->department_id)
                                  ->orWhere('user_id', $user->id);
                        })
                        ->orderBy('created_at', 'desc')
                        ->paginate(10);
                    
                    return response()->json($announcements);
                }
                
                // EMPLOYÉ : Voir annonces générales + son département + lui personnellement
                $employee = $user->employee;
                $query = Announcement::where('is_general', true);
                
                if ($employee) {
                    $query->orWhere('employee_id', $employee->id);
                    if ($employee->department_id) {
                        $query->orWhere('department_id', $employee->department_id);
                    }
                }
                
                $announcements = $query
                    ->with(['employee:id,first_name,last_name', 'department:id,name', 'creator:id,name'])
                    ->orderBy('created_at', 'desc')
                    ->paginate(10);
                    
                return response()->json($announcements);
            }
            
            return response()->json(['message' => 'Accès refusé'], 403);
            
        } catch (Throwable $th) {
            Log::error("Erreur dans AnnouncementController@index", ['details' => $th->getMessage()]);
            return response()->json([
                'error' => 'Failed to fetch announcements.',
                'details' => $th->getMessage(),
            ], 500);
        }
    }

    private function isManager($user): ?\App\Models\Manager
    {
        if (!$user || $user->role !== 'employee') {
            return null;
        }

        $employee = $user->employee;
        if (!$employee) {
            return null;
        }

        return \App\Models\Manager::where('employee_id', $employee->id)->first();
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'message' => 'required|string',
                'employee_id' => 'nullable|exists:employees,id',
                'department_id' => 'nullable|exists:departments,id',
                'is_general' => 'boolean',
            ]);

            $validated['user_id'] = $user->id;

            // ADMIN : Peut tout faire
            if ($user->role === 'admin') {
                // Vérifier la cohérence
                if (isset($validated['is_general']) && $validated['is_general']) {
                    $validated['employee_id'] = null;
                    $validated['department_id'] = null;
                } elseif (isset($validated['employee_id']) && $validated['employee_id']) {
                    $validated['is_general'] = false;
                    $validated['department_id'] = null;
                } elseif (isset($validated['department_id']) && $validated['department_id']) {
                    $validated['is_general'] = false;
                    $validated['employee_id'] = null;
                }
            }
            // MANAGER : Seulement son département ou un employé de son département
            elseif ($user->role === 'employee') {
                $manager = $this->isManager($user);

                if (!$manager) {
                    return response()->json([
                        'message' => 'Accès refusé. Seuls les managers et administrateurs peuvent créer des annonces.'
                    ], 403);
                }

                // Le manager peut publier pour tout son département
                if (isset($validated['department_id']) && $validated['department_id']) {
                    if ($validated['department_id'] != $manager->department_id) {
                        return response()->json([
                            'message' => 'Vous ne pouvez publier que dans votre département.'
                        ], 403);
                    }
                    $validated['is_general'] = false;
                    $validated['employee_id'] = null;
                }
                // Ou pour un employé spécifique de son département
                elseif (isset($validated['employee_id']) && $validated['employee_id']) {
                    $employee = Employee::find($validated['employee_id']);
                    if (!$employee || $employee->department_id != $manager->department_id) {
                        return response()->json([
                            'message' => 'Vous ne pouvez cibler que des employés de votre département.'
                        ], 403);
                    }
                    $validated['is_general'] = false;
                    $validated['department_id'] = null;
                }
                // Par défaut : tout le département du manager
                else {
                    $validated['is_general'] = false;
                    $validated['employee_id'] = null;
                    $validated['department_id'] = $manager->department_id;
                }
            } else {
                return response()->json(['message' => 'Accès refusé'], 403);
            }

            $announcement = Announcement::create($validated);
            $announcement->load(['employee', 'department', 'creator']);
            
            return response()->json($announcement, 201);
            
        } catch (Throwable $th) {
            Log::error("Erreur création annonce: " . $th->getMessage());
            return response()->json([
                'error' => 'Échec de la création', 
                'details' => $th->getMessage()
            ], 500);
        }
    }

    public function show(Announcement $announcement): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Admin peut tout voir
            if ($user->role === 'admin') {
                $announcement->load(['employee', 'department', 'creator']);
                return response()->json($announcement);
            }
            
            // Manager peut voir ses annonces + celles de son département
            if ($user->role === 'employee') {
                $manager = $this->isManager($user);
                
                if ($manager) {
                    if ($announcement->user_id !== $user->id && 
                        $announcement->department_id !== $manager->department_id && 
                        !$announcement->is_general) {
                        return response()->json(['message' => 'Accès refusé'], 403);
                    }
                } else {
                    // Employé simple : seulement s'il est concerné
                    $employee = $user->employee;
                    if (!$announcement->is_general && 
                        $announcement->employee_id !== $employee->id &&
                        $announcement->department_id !== $employee->department_id) {
                        return response()->json(['message' => 'Accès refusé'], 403);
                    }
                }
            }
            
            $announcement->load(['employee', 'department', 'creator']);
            return response()->json($announcement);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => 'Failed to retrieve announcement.',
                'details' => $th->getMessage(),
            ], 500);
        }
    }

    public function fetchMyAnnouncements(): JsonResponse
    {
        try {
            $user = Auth::user();
            $employee = Employee::where('user_id', $user->id)->first(); 

            if (!$employee) {
                $employeeId = null;
                $departmentId = null;
            } else {
                $employeeId = $employee->id;
                $departmentId = $employee->department_id;
            }

            $query = Announcement::where('is_general', true);

            if ($employeeId) {
                $query->orWhere('employee_id', $employeeId);
            }
            
            if ($departmentId) {
                $query->orWhere('department_id', $departmentId);
            }

            $announcements = $query
                ->with('employee:id,first_name,last_name', 'department:id,name', 'creator:id,name')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($announcements);
            
        } catch (Throwable $th) {
            Log::error("Erreur dans AnnouncementController@fetchMyAnnouncements", ['details' => $th->getMessage()]);
            return response()->json([
                'error' => 'Failed to fetch employee announcements.',
                'details' => $th->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, Announcement $announcement): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Seul le créateur ou un admin peut modifier
            if ($user->role !== 'admin' && $announcement->user_id !== $user->id) {
                return response()->json(['message' => 'Accès refusé. Vous ne pouvez modifier que vos propres annonces.'], 403);
            }
            
            $validated = $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'message' => 'sometimes|required|string',
                'employee_id' => 'nullable|exists:employees,id',
                'department_id' => 'nullable|exists:departments,id',
                'is_general' => 'boolean',
            ]);

            // Si c'est un manager (pas admin), vérifier les contraintes
            if ($user->role === 'employee') {
                $manager = $this->isManager($user);
                if (!$manager) {
                    return response()->json(['message' => 'Accès refusé'], 403);
                }

                // Appliquer les mêmes règles que pour la création
                if (isset($validated['department_id']) && $validated['department_id']) {
                    if ($validated['department_id'] != $manager->department_id) {
                        return response()->json(['message' => 'Vous ne pouvez cibler que votre département.'], 403);
                    }
                    $validated['is_general'] = false;
                    $validated['employee_id'] = null;
                } elseif (isset($validated['employee_id']) && $validated['employee_id']) {
                    $employee = Employee::find($validated['employee_id']);
                    if (!$employee || $employee->department_id != $manager->department_id) {
                        return response()->json(['message' => 'Vous ne pouvez cibler que des employés de votre département.'], 403);
                    }
                    $validated['is_general'] = false;
                    $validated['department_id'] = null;
                }
            }

            $announcement->update($validated);
            $announcement->load(['employee', 'department', 'creator']);

            return response()->json($announcement);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => 'Failed to update announcement.',
                'details' => $th->getMessage(),
            ], 500);
        }
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Seul le créateur ou un admin peut supprimer
            if ($user->role !== 'admin' && $announcement->user_id !== $user->id) {
                return response()->json(['message' => 'Accès refusé. Vous ne pouvez supprimer que vos propres annonces.'], 403);
            }
            
            $announcement->delete();
            return response()->json(null, 204);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => 'Failed to delete announcement.',
                'details' => $th->getMessage(),
            ], 500);
        }
    }
    
    public function checkManagerStatus(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role === 'admin') {
                return response()->json([
                    'is_manager' => true,
                    'is_admin' => true,
                    'department_id' => null,
                    'department_name' => null
                ]);
            }
            
            if ($user->role === 'employee') {
                $manager = $this->isManager($user);
                
                if ($manager) {
                    return response()->json([
                        'is_manager' => true,
                        'is_admin' => false,
                        'department_id' => $manager->department_id,
                        'department_name' => $manager->department->name ?? null
                    ]);
                }
            }
            
            return response()->json([
                'is_manager' => false,
                'is_admin' => false,
                'department_id' => null,
                'department_name' => null
            ]);
            
        } catch (Throwable $th) {
            Log::error("Erreur checkManagerStatus: " . $th->getMessage());
            return response()->json([
                'error' => 'Failed to check manager status.',
                'details' => $th->getMessage(),
            ], 500);
        }
    }
}
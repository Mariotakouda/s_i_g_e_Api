<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\PresenceController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ManagerController;
use App\Http\Controllers\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// 1. ROUTES PUBLIQUES
Route::post('/login', [AuthController::class, 'login']);

// 2. ROUTES PROTÉGÉES (Nécessitent d'être connecté)
Route::middleware('auth:sanctum')->group(function () {
        Route::get('employees', [EmployeeController::class, 'index']);

    // --- AUTHENTIFICATION & PROFIL ---
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/me', [EmployeeController::class, 'me']);
    Route::get('/check-manager-status', [EmployeeController::class, 'checkManagerStatus']);
    Route::post('/change-password', [AuthController::class, 'updatePassword']);

    // --- PHOTO DE PROFIL ---
    Route::post('/me/profile-photo', [EmployeeController::class, 'uploadPhoto']);
    Route::delete('/me/profile-photo', [EmployeeController::class, 'deletePhoto']);

    // --- ESPACE PERSONNEL (Tout employé) ---
    Route::get('/me/tasks', [TaskController::class, 'myTasks']);
    Route::get('/me/presences', [PresenceController::class, 'myPresences']);
    Route::get('/me/leave-requests', [LeaveRequestController::class, 'myLeaveRequests']);
    Route::get('/me/announcements', [AnnouncementController::class, 'myAnnouncements']);

    Route::get('/announcements', [AnnouncementController::class, 'index']);
    Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show']);

    Route::post('/presences/check-in', [PresenceController::class, 'store']);
    Route::put('/presences/{presence}/check-out', [PresenceController::class, 'update']);
    Route::post('/leave-requests', [LeaveRequestController::class, 'store']);

    // --- CONSULTATION TÂCHES (Détails & Rapports) ---
    Route::get('/tasks/{task}', [TaskController::class, 'show']);
    Route::post('/tasks/{task}/submit-report', [TaskController::class, 'submitReport']);
    Route::post('/tasks/{task}/mark-completed', [TaskController::class, 'markAsCompleted']);
    Route::get('/tasks/{task}/download-file', [TaskController::class, 'downloadTaskFile']);
    Route::get('/tasks/{task}/download-report', [TaskController::class, 'downloadReportFile']);

    // ============================================================
    // 3. ROUTES MANAGER & ADMIN (Gestion d'équipe)
    // ============================================================
    Route::middleware('is_manager')->group(function () {

        // --- GESTION DES EMPLOYÉS (Lecture seule pour Manager) ---
        Route::get('employees/{employee}', [EmployeeController::class, 'show']);

        // --- GESTION DES TÂCHES (Création/Assignation) ---
        Route::get('/tasks', [TaskController::class, 'index']);
        Route::post('/tasks', [TaskController::class, 'store']);
        Route::put('/tasks/{task}', [TaskController::class, 'update']);
        Route::get('/manager/team-tasks', [TaskController::class, 'managerTeamTasks']);

        // --- GESTION DES ANNONCES ---
        Route::post('/announcements', [AnnouncementController::class, 'store']);
        Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update']);

        // --- GESTION DES PRÉSENCES & CONGÉS ---
        Route::get('/presences', [PresenceController::class, 'index']);
        Route::get('/leave-requests', [LeaveRequestController::class, 'indexAdmin']);
        Route::put('/leave-requests/{leaveRequest}/approve', [LeaveRequestController::class, 'approve']);
        Route::put('/leave-requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject']);
    });

    // ============================================================
    // 4. ROUTES ADMIN UNIQUEMENT (Configuration Système)
    // ============================================================
    Route::middleware('admin')->group(function () {

        // --- GESTION COMPLÈTE (CRUD) ---
        Route::apiResource('employees', EmployeeController::class);
        Route::apiResource('departments', DepartmentController::class);
        Route::apiResource('roles', RoleController::class);
        Route::apiResource('managers', ManagerController::class);

        // Suppression des annonces
        Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy']);

        // --- SUPPRESSIONS DÉFINITIVES ---
        Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);
        Route::delete('/leave-requests/{id}', [LeaveRequestController::class, 'destroy']);
        Route::delete('/presences/{id}', [PresenceController::class, 'destroy']);
    });
});

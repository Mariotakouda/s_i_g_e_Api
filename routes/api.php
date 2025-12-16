<?php

use App\Http\Controllers\Api\AdminLeaveRequestController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\EmployeeRoleController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\PresenceController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\EmailController;
use App\Http\Controllers\Api\ManagerController;

// Définition d'une route POST simple pour l'envoi d'e-mail
Route::post('/emails/send-welcome', [EmailController::class, 'sendWelcomeEmail']);

// Routes publiques
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Routes authentifiées (employés)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Profil de l'employé connecté
    Route::prefix('me')->group(function () {
        Route::get('/', [EmployeeController::class, 'me']);
        Route::get('/tasks', [EmployeeController::class, 'myTasks']);
        Route::get('/presences', [EmployeeController::class, 'myPresences']);
        Route::get('/leave_requests', [EmployeeController::class, 'myLeaveRequests']);

        // Soumission d'une demande de congé par l'employé
        Route::post('/leave_requests', [LeaveRequestController::class, 'store']);

        Route::get('/announcements', [AnnouncementController::class, 'fetchMyAnnouncements']);
        Route::get('/departments', [EmployeeController::class, 'myDepartments']);
        Route::get('/roles', [EmployeeController::class, 'myRoles']);
    });
});

// Routes admin (SANS préfixe pour garder les URLs existantes)
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    // Departments
    Route::apiResource('departments', DepartmentController::class);

    // Employees
    Route::apiResource('employees', EmployeeController::class);

    // Roles
    Route::apiResource('roles', RoleController::class);
    Route::apiResource('employee_roles', EmployeeRoleController::class);

    // Tasks
    Route::apiResource('tasks', TaskController::class);

    // Managers
    Route::apiResource('managers', ManagerController::class);

    // Presences
    Route::apiResource('presences', PresenceController::class);

    // Announcements
    Route::apiResource('announcements', AnnouncementController::class);

    // ----------------------------------------------------
    // 🎯 GESTION DES DEMANDES DE CONGÉ
    // URL: /api/leave-requests (pour garder la cohérence avec vos autres routes)
    // ----------------------------------------------------

    // ⚠️ IMPORTANT: Les routes spécifiques DOIVENT être AVANT les routes avec paramètres
    // Route spécifique (Statistiques) - DOIT ÊTRE EN PREMIER
    Route::get('leave-requests/statistics', [LeaveRequestController::class, 'statistics'])
        ->name('admin.leave_requests.statistics');

    // Liste (GET /api/leave-requests)
    Route::get('leave-requests', [AdminLeaveRequestController::class, 'index'])
        ->name('admin.leave_requests.index');

    // Approuver (PUT /api/leave-requests/{id}/approve)
    Route::put('leave-requests/{leaveRequest}/approve', [AdminLeaveRequestController::class, 'approve'])
        ->name('admin.leave_requests.approve');
        
    // Rejeter (PUT /api/leave-requests/{id}/reject)
    Route::put('leave-requests/{leaveRequest}/reject', [AdminLeaveRequestController::class, 'reject'])
        ->name('admin.leave_requests.reject');

    // Supprimer (DELETE /api/leave-requests/{id})
    Route::delete('leave-requests/{leaveRequest}', [AdminLeaveRequestController::class, 'destroy'])
        ->name('admin.leave_requests.delete');
});
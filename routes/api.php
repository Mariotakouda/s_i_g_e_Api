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


// Public Routes
Route::post('/emails/send-welcome', [EmailController::class, 'sendWelcomeEmail']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


// Authenticated Routes (Employees & Managers)

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/update-password', [AuthController::class, 'updatePassword']);

    // --- GESTION DES PRÉSENCES (Pointage Employé) ---
    Route::post('/presences/check-in', [PresenceController::class, 'store']);
    Route::put('/presences/{presence}/check-out', [PresenceController::class, 'update']);
    Route::get('/presences/stats', [PresenceController::class, 'stats']);

    // --- PROFIL "ME" ---
    Route::prefix('me')->group(function () {
        Route::get('/', [EmployeeController::class, 'me']);
        Route::post('/photo', [EmployeeController::class, 'uploadPhoto']);
        Route::delete('/photo', [EmployeeController::class, 'deletePhoto']);
        Route::get('/tasks', [EmployeeController::class, 'myTasks']);
        Route::get('/presences', [EmployeeController::class, 'myPresences']);
        Route::get('/leave_requests', [EmployeeController::class, 'myLeaveRequests']);
        Route::post('/leave_requests', [LeaveRequestController::class, 'store']);
        Route::get('/announcements', [AnnouncementController::class, 'fetchMyAnnouncements']);
        Route::get('/departments', [EmployeeController::class, 'myDepartments']);
        Route::get('/roles', [EmployeeController::class, 'myRoles']);
    });

    // --- STATUT MANAGER & ANNONCES ---
    Route::get('/check-manager-status', [AnnouncementController::class, 'checkManagerStatus']);
    
    Route::prefix('announcements')->group(function () {
        Route::get('/', [AnnouncementController::class, 'index']);
        Route::post('/', [AnnouncementController::class, 'store']);
        Route::get('/{announcement}', [AnnouncementController::class, 'show']);
        Route::put('/{announcement}', [AnnouncementController::class, 'update']);
        Route::delete('/{announcement}', [AnnouncementController::class, 'destroy']);
    });
});

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::apiResource('departments', DepartmentController::class);
    Route::apiResource('employees', EmployeeController::class);
    Route::apiResource('roles', RoleController::class);
    Route::apiResource('employee_roles', EmployeeRoleController::class);
    Route::apiResource('tasks', TaskController::class);
    Route::apiResource('managers', ManagerController::class);
    
    Route::apiResource('presences', PresenceController::class)->except(['store', 'update']);

    // Leave Requests (Admin)
    Route::get('leave-requests/statistics', [LeaveRequestController::class, 'statistics'])
        ->name('admin.leave_requests.statistics');
    Route::get('leave-requests', [AdminLeaveRequestController::class, 'index'])
        ->name('admin.leave_requests.index');
    Route::put('leave-requests/{leaveRequest}/approve', [AdminLeaveRequestController::class, 'approve'])
        ->name('admin.leave_requests.approve');
    Route::put('leave-requests/{leaveRequest}/reject', [AdminLeaveRequestController::class, 'reject'])
        ->name('admin.leave_requests.reject');
    Route::delete('leave-requests/{leaveRequest}', [AdminLeaveRequestController::class, 'destroy'])
        ->name('admin.leave_requests.delete');
});
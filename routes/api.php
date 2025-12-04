<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\EmployeeRoleController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\PresenceController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\ManagerController;

// Routes publiques
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Routes authentifiées (employés)
Route::middleware('auth:sanctum')->group(function() {
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Profil de l'employé connecté
    Route::prefix('me')->group(function() {
        Route::get('/', [EmployeeController::class, 'me']);
        Route::get('/tasks', [EmployeeController::class, 'myTasks']);
        Route::get('/presences', [EmployeeController::class, 'myPresences']);
        Route::get('/leave_requests', [EmployeeController::class, 'myleave_requests']);
        Route::get('/announcements', [EmployeeController::class, 'myAnnouncements']);
        Route::get('/departments', [EmployeeController::class, 'myDepartments']);
        Route::get('/roles', [EmployeeController::class, 'myRoles']);
    });
});

// Routes admin
Route::middleware(['auth:sanctum', 'admin'])->group(function() {
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
    
    // ⚠️ IMPORTANT: Routes spécifiques AVANT apiResource
    // Sinon Laravel pense que "statistics" est un ID
    Route::get('leave_requests/statistics', [LeaveRequestController::class, 'statistics']);
    Route::apiResource('leave_requests', LeaveRequestController::class);
    
    // Announcements
    Route::apiResource('announcements', AnnouncementController::class);
});
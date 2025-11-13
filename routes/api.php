<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\EmployeeRoleController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\ManagerController;
use App\Http\Controllers\Api\PresenceController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\AnnouncementController;

//Routes publiques (auth)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

//Routes accessibles uniquement à l’admin
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::apiResource('departments', DepartmentController::class);
    Route::apiResource('employees', EmployeeController::class);
    Route::apiResource('roles', RoleController::class);
    Route::apiResource('employee_roles', EmployeeRoleController::class);
    Route::apiResource('tasks', TaskController::class);
    Route::apiResource('managers', ManagerController::class);
    Route::apiResource('presences', PresenceController::class);
    Route::apiResource('leave_requests', LeaveRequestController::class);
    Route::apiResource('announcements', AnnouncementController::class);
});

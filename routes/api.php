<?php

use App\Http\Controllers\Api\AnnouncementController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\EmployeeRoleController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\ManagerController;
use App\Http\Controllers\Api\PresenceController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\AuthController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);



Route::apiResource('departments', DepartmentController::class);
Route::apiResource('employees', EmployeeController::class);
Route::apiResource('tasks', TaskController::class);
Route::apiResource('managers', ManagerController::class);
Route::apiResource('presences', PresenceController::class);
Route::apiResource('leave_requests', LeaveRequestController::class);
Route::apiResource('roles', RoleController::class);
Route::apiResource('announcements', AnnouncementController::class);
Route::apiResource('employee_roles', EmployeeRoleController::class);



// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');



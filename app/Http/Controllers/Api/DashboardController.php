<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Department;
use App\Models\Task;
use App\Models\LeaveRequest;
use App\Models\Manager;
use App\Models\Role;
use App\Models\Announcement;
use App\Models\Presence;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'stats' => [
                'emp' => Employee::count(),
                'dep' => Department::count(),
                'task' => Task::count(),
                'leave' => LeaveRequest::count(),
                'managers' => Manager::count(),
                'roles' => Role::count(),
                'announcements' => Announcement::count(),
                'presences' => Presence::count(),
            ],
            'recent' => [
                'employees' => Employee::latest()->take(5)->get(['id', 'first_name', 'last_name', 'email']),
                'announcements' => Announcement::latest()->take(5)->get(['id', 'title', 'message']),
                'presences' => Presence::with('employee:id,first_name,last_name')
                    ->latest()
                    ->take(5)
                    ->get(['id', 'employee_id', 'date', 'status']),
            ]
        ]);
    }
}

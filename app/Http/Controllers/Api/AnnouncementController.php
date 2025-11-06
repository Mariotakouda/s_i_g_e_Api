<?php

namespace App\Http\Controllers\Api;

use App\Models\Announcement;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AnnouncementController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $announcements = Announcement::with('employee:id,first_name,last_name')
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            return response()->json($announcements);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => 'Failed to fetch announcements.',
                'details' => $th->getMessage(), // 🔍 message d’erreur précis
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'message' => 'required|string',
                'employee_id' => 'required|exists:employees,id',
            ]);

            $announcement = Announcement::create($validated);

            return response()->json($announcement, 201);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => 'Failed to create announcement.',
                'details' => $th->getMessage(), // 🔍 message d’erreur détaillé
            ], 500);
        }
    }

    public function show(Announcement $announcement): JsonResponse
    {
        try {
            $announcement->load('employee');
            return response()->json($announcement);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => 'Failed to retrieve announcement.',
                'details' => $th->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, Announcement $announcement): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'message' => 'sometimes|required|string',
            ]);

            $announcement->update($validated);

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
            $announcement->delete();
            return response()->json(null, 204);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => 'Failed to delete announcement.',
                'details' => $th->getMessage(),
            ], 500);
        }
    }
}

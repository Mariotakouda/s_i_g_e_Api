<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Employee;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuthController extends Controller
{
    // REGISTER (EMPLOYÉ)
    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|min:3',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:8',
            ]);

            // Création du User
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'employee',
            ]);

            // Création automatique de l'Employee
            $employee = Employee::create([
                'user_id' => $user->id,
                'first_name' => $request->name,
                'email' => $request->email,
            ]);

            return response()->json([
                'message' => 'Employé créé avec succès',
                'user' => $user,
                'employee' => $employee,
            ], 201);

        } catch (Throwable $e) {
            Log::error('Erreur lors de l\'inscription : ' . $e->getMessage());
            return response()->json([
                'message' => 'Une erreur est survenue lors de l\'inscription',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // LOGIN
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json(['message' => 'Identifiants incorrects'], 401);
            }

            $token = $user->createToken('api_token')->plainTextToken;

            // Profil complet pour les employés
            $employee = null;
            if ($user->role === 'employee') {
                $employee = $user->employee()->with(['department', 'roles'])->first();
            }

            return response()->json([
                'message' => 'Connexion réussie',
                'token' => $token,
                'user' => $user,
                'employee' => $employee,
            ]);

        } catch (Throwable $e) {
            Log::error('Erreur lors de la connexion : ' . $e->getMessage());
            return response()->json([
                'message' => 'Une erreur est survenue lors de la connexion',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // LOGOUT
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();
            return response()->json(['message' => 'Déconnexion réussie']);
        } catch (Throwable $e) {
            Log::error('Erreur lors de la déconnexion : ' . $e->getMessage());
            return response()->json([
                'message' => 'Une erreur est survenue lors de la déconnexion',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

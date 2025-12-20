<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Employee;
use Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuthController extends Controller
{
    // REGISTER (EMPLOYÉ)
    // public function register(Request $request)
    // {
    //     try {
    //         $request->validate([
    //             'name' => 'required|string|min:3',
    //             'email' => 'required|email|unique:users,email',
    //             'password' => 'required|string|min:8',
    //         ]);

    //         // Création du User
    //         $user = User::create([
    //             'name' => $request->name,
    //             'email' => $request->email,
    //             'password' => Hash::make($request->password),
    //             'role' => 'employee',
    //         ]);

    //         // Création automatique de l'Employee
    //         $employee = Employee::create([
    //             'user_id' => $user->id,
    //             'first_name' => $request->name,
    //             'email' => $request->email,
    //         ]);

    //         return response()->json([
    //             'message' => 'Employé créé avec succès',
    //             'user' => $user,
    //             'employee' => $employee,
    //         ], 201);

    //     } catch (Throwable $e) {
    //         Log::error('Erreur lors de l\'inscription : ' . $e->getMessage());
    //         return response()->json([
    //             'message' => 'Une erreur est survenue lors de l\'inscription',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

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

        $employee = null;
        if ($user->role === 'employee') {
            $employee = $user->employee()->with(['department', 'roles'])->first();
        }

        return response()->json([
            'message' => 'Connexion réussie',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'needs_password_change' => $user->needs_password_change, // On envoie l'info à React
            ],
            'employee' => $employee,
        ]);
    } catch (Throwable $e) {
        Log::error('Erreur login : ' . $e->getMessage());
        return response()->json(['message' => 'Erreur interne'], 500);
    }
}

public function updatePassword(Request $request) 
{
    $request->validate([
        'current_password' => 'required',
        'new_password' => 'required|string|min:8|confirmed',
    ]);

    /** @var \App\Models\User $user */
    $user = Auth::user();

    if (!Hash::check($request->current_password, $user->password)) {
        return response()->json(['message' => 'L\'ancien mot de passe est incorrect.'], 422);
    }

    $user->password = Hash::make($request->new_password);
    $user->needs_password_change = false; // L'employé a maintenant changé son mot de passe
    $user->save();

    return response()->json(['message' => 'Mot de passe mis à jour avec succès.']);
}
}
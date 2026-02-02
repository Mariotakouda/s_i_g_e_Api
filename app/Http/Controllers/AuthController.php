<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Employee;
use Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Str;
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

/**
 * Déconnexion de l'utilisateur (Suppression du token)
 */
public function logout(Request $request)
{
    try {
        // Supprime le token actuel utilisé pour la requête
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnexion réussie'
        ], 200);
    } catch (\Throwable $e) {
        return response()->json([
            'message' => 'Erreur lors de la déconnexion',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function sendResetLinkEmail(Request $request)
{
    $request->validate(['email' => 'required|email']);

    // Utilise le système natif de Laravel pour générer le token et envoyer le mail
    $status = Password::sendResetLink($request->only('email'));

    if ($status === Password::RESET_LINK_SENT) {
        return response()->json(['message' => 'Lien de réinitialisation envoyé par email !'], 200);
    }

    return response()->json(['message' => 'Impossible d\'envoyer le lien (email introuvable ou erreur serveur).'], 400);
}

/**
 * Traite la modification finale du mot de passe
 */
public function resetPassword(Request $request)
{
    $request->validate([
        'token' => 'required',
        'email' => 'required|email',
        'password' => 'required|string|min:8|confirmed',
    ]);

    $status = Password::reset(
        $request->only('email', 'password', 'password_confirmation', 'token'),
        function ($user, $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                'needs_password_change' => false, // On débloque l'utilisateur
            ])->setRememberToken(Str::random(60));
            $user->save();
        }
    );

    if ($status === Password::PASSWORD_RESET) {
        return response()->json(['message' => 'Votre mot de passe a été réinitialisé avec succès.'], 200);
    }

    return response()->json(['message' => 'Le lien est invalide ou a expiré.'], 400);
}

}
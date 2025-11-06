<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Exception;

class AuthController extends Controller
{

    public function register(Request $request) {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8'
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            return response()->json([
                "message" => "Inscrit avec succès",
                "description" => $user
            ], 201);

        } catch (ValidationException $e) {
            Log::error('Erreur de validation lors de l\'inscription: ' . $e->getMessage());
            return response()->json([
                "message" => "Erreur de validation",
                "errors" => $e->errors()
            ], 422);

        } catch (Exception $e) {
            Log::error('Erreur lors de l\'inscription: ' . $e->getMessage());
            return response()->json([
                "message" => "Une erreur s'est produite lors de l'inscription",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string'
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    "message" => "Identifiants incorrects"
                ], 401);
            }

            return response()->json([
                "message" => "Connexion avec succès",
                "description" => $user
            ], 200);

        } catch (ValidationException $e) {
            Log::error('Erreur de validation lors de la connexion: ' . $e->getMessage());
            return response()->json([
                "message" => "Erreur de validation",
                "errors" => $e->errors()
            ], 422);

        } catch (Exception $e) {
            Log::error('Erreur lors de la connexion: ' . $e->getMessage());
            return response()->json([
                "message" => "Une erreur s'est produite lors de la connexion",
                "error" => $e->getMessage()
            ], 500);
        }
    }
}
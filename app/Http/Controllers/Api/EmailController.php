<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\UserWelcomeEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class EmailController extends Controller
{
    public function sendWelcomeEmail(Request $request)
    {
        // 1. Validation des données
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'name'     => 'required|string|max:255',
            'password' => 'required|string', // Ajoutez ceci si vous testez via ce contrôleur
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $validatedData = $validator->validated();

        try {
    Mail::to($validatedData['email'])
        ->send(new UserWelcomeEmail(
            $validatedData['name'],
            $validatedData['password'],
            $validatedData['email'] // On passe l'email ici aussi
        ));

    return response()->json([
        'message' => 'L\'e-mail de bienvenue a été envoyé avec succès.',
    ], 200);
        } catch (\Exception $e) {
            // 4. Gestion des erreurs (ex: problème de connexion SMTP)
            // En développement, vous pouvez utiliser $e->getMessage() pour le débogage.
            return response()->json([
                'error' => 'Échec de l\'envoi de l\'e-mail.',
                'details' => $e->getMessage() // À ne pas afficher en production !
            ], 500);
        }
    }
}

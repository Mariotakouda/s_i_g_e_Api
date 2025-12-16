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
            'email' => 'required|email',
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $validatedData = $validator->validated();

        try {
            // 2. Envoi de l'e-mail
            // Mail::to() définit le destinataire. send() utilise votre Mailable.
            Mail::to($validatedData['email'])
                ->send(new UserWelcomeEmail($validatedData['name']));

            // 3. Réponse en cas de succès
            return response()->json([
                'message' => 'L\'e-mail de bienvenue a été mis en file d\'attente (ou envoyé) avec succès.',
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
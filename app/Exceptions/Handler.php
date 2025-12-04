<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontReport = [];

    protected $dontFlash = ['password'];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Convertir une exception d'authentification en réponse JSON pour l'API
     * 
     * CORRECTION: Ceci empêche l'erreur "Route [login] not defined"
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        // Si c'est une requête API, retourner JSON au lieu de rediriger
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Non authentifié.',
                'error' => 'Vous devez être connecté pour effectuer cette action.'
            ], 401);
        }

        // Pour les requêtes web normales, rediriger vers login
        // (seulement si vous avez une route 'login' définie)
        return redirect()->guest('/login');
    }
}
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Vérifie si l'utilisateur authentifié est un administrateur.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Vérifie si un utilisateur est connecté et si son rôle est 'admin'
        if ($request->user() && $request->user()->role === 'admin') {
            return $next($request);
        }

        // Si ce n’est pas un admin
        return response()->json([
            'message' => 'Accès refusé — réservé aux administrateurs.'
        ], 403);
    }
}

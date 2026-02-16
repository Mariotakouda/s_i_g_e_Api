<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;
use App\Models\Manager;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        $userRole = strtolower($user->role);
        $hasEmployee = $user->employee()->exists();

        Log::info('Vérification accès', [
            'user_id'        => $user->id,
            'role_original'  => $user->role,
            'role_normalisé' => $userRole,
            'has_employee'   => $hasEmployee,
        ]);

        // Vérification directe sur le rôle utilisateur
        if (in_array($userRole, ['admin', 'manager'])) {
            Log::info('Accès autorisé via rôle utilisateur', ['role' => $user->role]);
            return $next($request);
        }

        // Vérification via employé
        if ($hasEmployee) {
            $employee = $user->employee;

            $hasManagerRole = $employee->roles()
                ->whereRaw('LOWER(name) = ?', ['manager'])
                ->exists();

            $existsInManagersTable = Manager::where('employee_id', $employee->id)->exists();

            Log::info('🔍 Vérification rôles employé', [
                'employee_id'       => $employee->id,
                'has_manager_role'  => $hasManagerRole,
                'in_managers_table' => $existsInManagersTable,
            ]);

            if ($hasManagerRole || $existsInManagersTable) {
                Log::info('Accès autorisé via rôle employé');
                return $next($request);
            }
        }

        // Accès refusé
        Log::warning('Accès refusé', [
            'user_id'  => $user->id,
            'role'     => $user->role,
            'endpoint' => $request->path(),
        ]);

        return response()->json([
            'message' => 'Accès refusé — réservé aux administrateurs et managers.',
            'debug'   => [
                'user_id'            => $user->id,
                'role_on_user_table' => $user->role,
                'has_employee_record'=> $hasEmployee,
            ],
        ], 403);
    }
}

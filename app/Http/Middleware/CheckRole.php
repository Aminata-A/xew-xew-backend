<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'non_authentifie',
                    'message' => config('messages.authentification.errors.token.not_authenticated')
                ], 401);
            }

            // Vérifier si l'utilisateur a un des rôles requis
            if (!in_array($user->userable->role, $roles)) {
                return response()->json([
                    'success' => false,
                    'error' => 'acces_refuse',
                    'message' => config('messages.authentification.errors.role.unauthorized')
                ], 403);
            }

            return $next($request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'erreur_inattendue',
                'message' => config('messages.authentification.errors.general.unexpected')
            ], 500);
        }
    }
}

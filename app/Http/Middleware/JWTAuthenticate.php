<?php

namespace App\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class JWTAuthenticate
{
    public function handle($request, Closure $next)
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
        } catch (TokenExpiredException $e) {
            return response()->json([
                'success' => false,
                'error' => 'token_expire',
                'message' => config('messages.authentification.errors.token.expired')
            ], 401);
        } catch (TokenInvalidException $e) {
            return response()->json([
                'success' => false,
                'error' => 'token_invalide',
                'message' => config('messages.authentification.errors.token.invalid')
            ], 401);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'error' => 'token_manquant',
                'message' => config('messages.authentification.errors.token.missing')
            ], 401);
        }

        return $next($request);
    }
}

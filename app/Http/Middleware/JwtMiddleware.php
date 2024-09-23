<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Firebase\JWT\Key;
use Tymon\JWTAuth\JWT;
use Illuminate\Http\Request;
use Firebase\JWT\ExpiredException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

class JwtMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        try {
            $decoded = JWTAuth::decode($token, new Key(config('jwt.secret'), 'HS256'));
        }
        catch (ExpiredException $e) {
            return response()->json(['error' => 'Token expired'], 401);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Token invalid'], 401);
        }

        return $next($request);
    }
}

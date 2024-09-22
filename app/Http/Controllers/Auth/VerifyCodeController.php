<?php

namespace App\Http\Controllers\Auth;

use Firebase\JWT\JWT;
use Tymon\JWTAuth\Payload;
use Illuminate\Http\Request;
use Tymon\JWTAuth\PayloadFactory;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redis;

class VerifyCodeController extends Controller
{
    private $jwtSecret;

    public function __construct()
    {
        $this->jwtSecret = env('JWT_SECRET');
    }
    public function verifyCode(Request $request)
    {
        $email = $request->input('email');
        $inputCode = $request->input('code');

        // Check if email is provided
        if (!$email) {
            return response()->json(['error' => 'Email is required'], 400);
        }

        // Check if email is provided
        if (!$inputCode) {
            return response()->json(['error' => 'Code is required'], 400);
        }

        // Retrieve the code from Redis
        $storedCode = Redis::get("verification_code:{$email}");

        // Check if the code exists and matches
        if (!$storedCode) {
            return response()->json(['error' => 'No code found or code expired'], 404);
        }

        if ($storedCode != $inputCode) {
            return response()->json(['error' => 'Invalid code'], 400);
        }

        //  generer token jwt pour l'utilisateur apres avoir recu le code pour la cnontinite du creation de compte
        $token = $this->generateRegisterJWT($email);


        return response()->json(['message' => 'Code verified successfully', 'token' => $token],200);
    }

    private function generateRegisterJWT($email)
    {
        // Define the payload for the JWT
        $payload = [
            'iss' => 'your_app_name', // Issuer of the token (your app)
            'email' => $email,        // The email of the user
            'iat' => time(),          // Issued at timestamp
            'exp' => time() + 3600    // Expiration time (1 hour)
        ];

        // Encode the payload into a JWT token
        $jwt = JWT::encode($payload, $this->jwtSecret, 'HS256');

        return $jwt;
    }
}

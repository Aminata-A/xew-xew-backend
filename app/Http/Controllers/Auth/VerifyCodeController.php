<?php

namespace App\Http\Controllers\Auth;

use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redis;

class VerifyCodeController extends Controller
{
    public function verifyCode(Request $request)
    {
        // Récupérer l'email et le code à partir de la requête
        $email = $request->input('email');
        $inputCode = $request->input('code');

        // Vérifier si l'email est fourni
        if (!$email) {
            return response()->json(['error' => 'L\'email est requis.'], 400);
        }

        // Vérifier si le code est fourni
        if (!$inputCode) {
            return response()->json(['error' => 'Le code est requis.'], 400);
        }

        // Récupérer le code stocké dans Redis
        $storedCode = Redis::get("verification_code:{$email}");

        // Vérifier si le code existe et correspond
        if (!$storedCode) {
            return response()->json(['error' => 'Aucun code trouvé ou le code a expiré.'], 404);
        }

        if ($storedCode != $inputCode) {
            return response()->json(['error' => 'Code invalide.'], 400);
        }

        // Générer un token JWT après la vérification du code
        $token = $this->generateRegisterJWT($email);

        return response()->json(['message' => 'Code vérifié avec succès', 'token' => $token], 200);
    }

    private function generateRegisterJWT($email)
    {
        $jwtSecret = config('jwt.secret');

        // Définir le payload du JWT
        $payload = [
            'iss' => 'xew_xew',      // Émetteur du token (votre app)
            'email' => $email,       // L'email de l'utilisateur
            'iat' => time(),         // Timestamp de l'émission
            'exp' => time() + 86400  // Expiration (1 jour pour les tests)
        ];

        // Encoder le payload en JWT
        $jwt = JWT::encode($payload, $jwtSecret, 'HS256');

        return $jwt;
    }
}

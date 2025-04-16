<?php

namespace App\Http\Controllers\Auth;

use Firebase\JWT\JWT;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redis;
use App\Http\Requests\Auth\VerifyCodeRequest;

class VerifyCodeController extends Controller
{
    public function verifyCode(VerifyCodeRequest $request)
    {
        // Récupérer l'email et le code à partir de la requête
        $email = $request->input('email');
        $inputCode = $request->input('code');

        // Récupérer le code stocké dans Redis
        $storedCode = Redis::get("verification_code:{$email}");

        // Vérifier si le code existe et correspond
        if (!$storedCode) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun code trouvé ou le code a expiré.'
            ], 404);
        }

        if ($storedCode != $inputCode) {
            return response()->json([
                'success' => false,
                'message' => 'Code invalide.'
            ], 400);
        }

        // Générer un token JWT après la vérification du code
        $token = $this->generateRegisterJWT($email);

        // Supprimer le code de Redis après utilisation
        Redis::del("verification_code:{$email}");

        return response()->json([
            'success' => true,
            'message' => 'Code vérifié avec succès',
            'token' => $token
        ], 200);
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
        return JWT::encode($payload, $jwtSecret, 'HS256');
    }
}

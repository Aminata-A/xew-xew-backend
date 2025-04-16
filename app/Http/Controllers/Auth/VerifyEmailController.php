<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Mail\SendCodeEmail;
use App\Models\RegisteredUser;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use App\Http\Requests\Auth\VerifyEmailRequest;

class VerifyEmailController extends Controller
{
    public function verify(VerifyEmailRequest $request)
    {
        $id = null;
        $email = $request->input('email');

        // Vérifier si l'utilisateur existe
        $user = User::where('email', $email)->first();

        if ($user) {
            // Vérifier si l'utilisateur est un RegisteredUser
            if ($user->userable instanceof RegisteredUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Un utilisateur avec cet email existe déjà.'
                ], 200);
            }
            $id = $user->id;
        }

        // Générer un code à 6 chiffres
        $code = rand(100000, 999999);

        // Stocker le code dans Redis avec une expiration (10 minutes)
        $redisKey = "verification_code:{$email}";

        // Stocker le code comme une chaîne de caractères
        Redis::set($redisKey, (string)$code);
        Redis::expire($redisKey, 600); // 10 minutes

        // Vérifier si le code a été correctement stocké
        $storedCode = Redis::get($redisKey);
        if (!$storedCode) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du stockage du code.',
                'debug' => [
                    'email' => $email,
                    'code' => $code,
                    'redis_key' => $redisKey,
                    'stored_code' => $storedCode
                ]
            ], 500);
        }

        // Envoyer le code par email
        Mail::to($email)->send(new SendCodeEmail($code));

        return response()->json([
            'success' => true,
            'message' => 'Code envoyé à l\'adresse email fournie',

        ], 200);
    }
}

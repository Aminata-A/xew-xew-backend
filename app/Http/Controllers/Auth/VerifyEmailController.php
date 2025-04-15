<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Mail\SendCodeEmail;
use Illuminate\Http\Request;
use App\Models\RegisteredUser;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;

class VerifyEmailController extends Controller
{
    public function verify(Request $request)
    {
        $id = null;
        // Récupérer l'email de la requête
        $email = $request->input('email');

        // Vérifier si l'email est fourni
        if (!$email) {
            return response()->json([
                'success' => false,
                'error' => config('messages.authentification.errors.validation.email.required')
            ], 400);
        }

        // Valider l'email avec une expression régulière
        $emailRegex = "/^[a-zA-Z0-9.+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/";

        if (!preg_match($emailRegex, $email)) {
            return response()->json([
                'success' => false,
                'error' => config('messages.authentification.errors.validation.email.invalid')
            ], 400);
        }

        // Vérifier si l'utilisateur existe
        $user = User::where('email', $email)->first();

        if ($user) {
            // Vérifier si la relation userable est un RegisteredUser
            if ($user->userable instanceof RegisteredUser) {
                return response()->json([
                    'success' => true,
                    'message' => config('messages.authentification.errors.account.already_verified')
                ], 200);
            }
            $id = $user->id;
        }

        // Générer un code à 6 chiffres
        $code = rand(100000, 999999);

        // Stocker le code dans Redis avec un temps d'expiration (10 minutes)
        Redis::set("verification_code:{$email}", $code, 'EX', 600);

        // Envoyer le code à l'email fourni
        try {
            Mail::to($email)->send(new SendCodeEmail($code));
            return response()->json([
                'success' => true,
                'message' => config('messages.authentification.success.email_verification_sent'),
                'id' => $id
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => config('messages.authentification.errors.email.verification_failed')
            ], 500);
        }
    }
}

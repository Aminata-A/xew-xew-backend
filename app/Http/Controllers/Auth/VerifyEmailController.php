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
        // Récupérer l'email à partir de la requête
        $email = $request->input('email');

        // Vérifier si l'email est fourni
        if (!$email) {
            return response()->json(['error' => 'L\'email est requis.'], 400);
        }

        // Valider le format de l'email avec une regex
        $emailRegex = "/^[a-zA-Z0-9.+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/";

        if (!preg_match($emailRegex, $email)) {
            return response()->json(['error' => 'Le format de l\'email est invalide.'], 400);
        }

        // Vérifier si l'utilisateur existe
        $user = User::where('email', $email)->first();

        if ($user) {
            // Vérifier si la relation userable est un RegisteredUser
            if ($user->userable instanceof RegisteredUser) {
                return response()->json(['message' => 'Cet utilisateur existe déjà en tant que RegisteredUser.'], 200);
            }
            $id = $user->id;
        }

        // Générer un code à 6 chiffres
        $code = rand(100000, 999999);

        // Stocker le code dans Redis avec un temps d'expiration (par exemple, 10 minutes)
        Redis::set("verification_code:{$email}", $code, 'EX', 600); // 600 secondes = 10 minutes

        // Envoyer le code à l'adresse email fournie
        Mail::to($email)->send(new SendCodeEmail($code));

        return response()->json(['message' => 'Code envoyé à l\'adresse email fournie.', 'id' => $id], 200);
    }
}

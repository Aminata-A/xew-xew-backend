<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use App\Models\RegisteredUser;
use App\Http\Controllers\Controller;
use App\Models\AnonymousUser;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthentificationController extends Controller
{
    public function register(Request $request)
    {
        $jwtSecret = config('jwt.secret');

        // Récupérer le token JWT de l'en-tête de la requête
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['erreur' => 'Token manquant'], 400);
        }

        try {
            // Décoder le token pour récupérer l'email
            $decodedToken = JWT::decode($token, new Key($jwtSecret, 'HS256'));
            $email = $decodedToken->email;

        } catch (\Exception $e) {
            return response()->json(['erreur' => 'Token invalide ou expiré'], 401);
        }

        // Validation manuelle des champs
        $errors = [];

        if (empty($request->input('name'))) {
            $errors['name'] = 'Le nom est requis.';
        } elseif (strlen($request->input('name')) > 255) {
            $errors['name'] = 'Le nom ne doit pas dépasser 255 caractères.';
        }

        if (empty($request->input('password'))) {
            $errors['password'] = 'Le mot de passe est requis.';
        } elseif (strlen($request->input('password')) < 6) {
            $errors['password'] = 'Le mot de passe doit contenir au moins 6 caractères.';
        } elseif ($request->input('password') !== $request->input('password_confirmation')) {
            $errors['password_confirmation'] = 'La confirmation du mot de passe ne correspond pas.';
        }

        if (empty($request->input('phone'))) {
            $errors['phone'] = 'Le numéro de téléphone est requis.';
        } elseif (strlen($request->input('phone')) > 20) {
            $errors['phone'] = 'Le numéro de téléphone ne doit pas dépasser 20 caractères.';
        }

        if (empty($request->input('role'))) {
            $errors['role'] = 'Le rôle est requis.';
        } elseif (!in_array($request->input('role'), ['organizer', 'participant'])) {
            $errors['role'] = 'Le rôle doit être soit "admin" soit "user".';
        }

        // Vérifier s'il y a des erreurs de validation
        if (!empty($errors)) {
            return response()->json(['erreurs' => $errors], 422);
        }

        // Vérifier si l'utilisateur avec cet email existe déjà
        $user = User::where('email', $email)->first();

        if ($user) {
            return response()->json(['erreur' => 'Un utilisateur avec cet email existe déjà'], 400);
        }

        // Créer l'utilisateur inscrit
        $registeredUser = new RegisteredUser([
            'role' => $request->input('role'),
            'password' => Hash::make($request->input('password')),
            'solde' => 0,
            'status' => 'active',
        ]);
        $registeredUser->save();

        // Associer l'utilisateur de base au RegisteredUser
        $user = new User([
            'name' => $request->input('name'),
            'email' => $email,
            'phone' => $request->input('phone'),
        ]);
        $user->userable()->associate($registeredUser);
        $user->save();

        return response()->json(['message' => 'Inscription réussie'], 201);
    }



    public function login(Request $request)
    {
        // Validation manuelle des champs
        $errors = [];

        if (empty($request->input('email'))) {
            $errors['email'] = 'L\'email est requis.';
        } elseif (!filter_var($request->input('email'), FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Format d\'email invalide.';
        }

        if (empty($request->input('password'))) {
            $errors['password'] = 'Le mot de passe est requis.';
        } elseif (strlen($request->input('password')) < 8) {
            $errors['password'] = 'Le mot de passe doit contenir au moins 8 caractères.';
        }

        // Vérifier s'il y a des erreurs de validation
        if (!empty($errors)) {
            return response()->json(['erreurs' => $errors], 422);
        }

        $email = $request->input('email');
        $password = $request->input('password');

        // Récupérer l'utilisateur avec cet email
        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json(['erreur' => 'Email invalide'], 401);
        }

        // Vérifier si l'utilisateur est un AnonymousUser
        if ($user->userable instanceof AnonymousUser) {
            return response()->json(['erreur' => "Ce compte n'existe pas"], 401);
        }

        // Vérifier le mot de passe
        $registeredUser = $user->userable;

        if (!Hash::check($password, $registeredUser->password)) {
            return response()->json(['erreur' => 'Mot de passe invalide'], 401);
        }

        // Vérifier si le compte est actif
        if ($registeredUser->status !== 'active') {
            return response()->json(['erreur' => 'Votre compte est inactif'], 401);
        }

        // Générer le token JWT
        $token = JWTAuth::fromUser($registeredUser);

        return response()->json(['message' => 'Connexion réussie', 'token' => $token], 200);
    }

    // public function getUserProfile(Request $request)
    // {
    //     $user = JWTAuth::parseToken()->authenticate(); // Récupérer l'utilisateur connecté à partir du token

    //     return response()->json($user, 200); // Retourner les informations de l'utilisateur


    // }

    public function getUserProfile(Request $request)
    {
        try {
            // Vérifier si un token JWT est présent et authentifier l'utilisateur
            if (! $user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['message' => 'Vous devez être connecté pour voir vos informations.'], 401);
            }

            // Récupérer les informations utilisateur
            $userProfile = $user; // Accéder à la relation userable

            // Retourner les informations combinées dans un seul tableau
            return response()->json([
                'id' => $user->id,
                'name' => $user->user->name ?? $userProfile->user->name,
                'email' => $user->user->email,
                'phone' => $user->user->phone,
                'role' => $userProfile->role ?? null,
                'balance' => $userProfile->balance ?? null,
                'status' => $userProfile->status ?? null,
                'photo' => $userProfile->photo ?? null,
            ], 200);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json(['error' => 'Token expiré, veuillez vous reconnecter.'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(['error' => 'Token invalide.'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['error' => 'Le token est absent.'], 401);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de la récupération du profil utilisateur : ' . $e->getMessage()], 500);
        }
    }



    public function logout(Request $request)
    {
        $request->user()->token()->revoke();
        return response()->json(['message' => 'Connexion annulée'], 200);
    }

}

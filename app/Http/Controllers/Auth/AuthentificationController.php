<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\AnonymousUser;
use App\Models\RegisteredUser;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;

class AuthentificationController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $jwtSecret = config('jwt.secret');

        // Récupérer le token JWT de l'en-tête de la requête
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token manquant'
            ], 400);
        }

        try {
            // Décoder le token pour récupérer l'email
            $decodedToken = JWT::decode($token, new Key($jwtSecret, 'HS256'));
            $email = $decodedToken->email;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token invalide ou expiré'
            ], 401);
        }

        // Vérifier si l'utilisateur avec cet email existe déjà
        $user = User::where('email', $email)->first();

        if ($user) {
            return response()->json([
                'success' => false,
                'message' => 'Un utilisateur avec cet email existe déjà'
            ], 400);
        }

        // Gestion de l'upload de la photo de profil
        $profilePhotoPath = null;
        if ($request->hasFile('photo')) {
            $photo = $request->file('photo');
            if ($photo->isValid()) {
                $photoName = time() . '_' . $photo->getClientOriginalName();
                $profilePhotoPath = $photo->storeAs('uploads/profile_photos', $photoName, 'public');
            }
        }

        // Créer l'utilisateur inscrit
        $registeredUser = new RegisteredUser([
            'role' => $request->input('role'),
            'password' => Hash::make($request->input('password')),
            'balance' => 0,
            'status' => 'active',
            'photo' => $profilePhotoPath ? '/storage/' . $profilePhotoPath : null,
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

        return response()->json([
            'success' => true,
            'message' => 'Inscription réussie'
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $email = $request->input('email');
        $password = $request->input('password');

        // Récupérer l'utilisateur avec cet email
        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Email invalide'], 401);
        }

        // Vérifier si l'utilisateur est un AnonymousUser
        if ($user->userable instanceof AnonymousUser) {
            return response()->json(['success' => false, 'message' => "Ce compte n'existe pas"], 401);
        }

        // Vérifier le mot de passe
        $registeredUser = $user->userable;

        if (!Hash::check($password, $registeredUser->password)) {
            return response()->json(['success' => false, 'message' => 'Mot de passe invalide'], 401);
        }

        // Vérifier si le compte est actif
        if ($registeredUser->status !== 'active') {
            return response()->json(['success' => false, 'message' => 'Votre compte est inactif'], 401);
        }

        // Générer le token JWT
        $token = JWTAuth::fromUser($registeredUser);

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie',
            'token' => $token
        ], 200);
    }

    public function getUserProfile()
    {
        try {
            // Vérifier si un token JWT est présent et authentifier l'utilisateur
            if (! $user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['success' => false, 'message' => 'Vous devez être connecté pour voir vos informations.'], 401);
            }

            // Récupérer les informations utilisateur
            $userProfile = $user;

            // Retourner les informations combinées dans un seul tableau
            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->user->name ?? $userProfile->user->name,
                    'email' => $user->user->email,
                    'phone' => $user->user->phone,
                    'role' => $userProfile->role ?? null,
                    'balance' => $userProfile->balance ?? null,
                    'status' => $userProfile->status ?? null,
                    'photo' => $userProfile->photo ?? null,
                ]
            ], 200);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json(['success' => false, 'message' => 'Token expiré, veuillez vous reconnecter.'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(['success' => false, 'message' => 'Token invalide.'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['success' => false, 'message' => 'Le token est absent.'], 401);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur lors de la récupération du profil utilisateur.'], 500);
        }
    }

    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());
        return response()->json(['success' => true, 'message' => 'Déconnexion réussie'], 200);
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            // Mettre à jour les informations de l'utilisateur
            if ($request->has('name')) {
                $user->name = $request->input('name');
            }

            if ($request->has('phone')) {
                $user->phone = $request->input('phone');
            }

            // Gestion de la photo de profil
            if ($request->hasFile('photo')) {
                // Supprimer l'ancienne photo si elle existe
                if ($user->photo) {
                    Storage::disk('public')->delete($user->photo);
                }

                // Sauvegarder la nouvelle photo
                $photoPath = $request->file('photo')->store('profile_photos', 'public');
                $user->photo = $photoPath;
            }

            // Sauvegarder les changements
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Profil mis à jour avec succès',
                'user' => $user
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur lors de la mise à jour du profil.'], 500);
        }
    }
}

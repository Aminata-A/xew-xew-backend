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
use App\Models\Category;

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

        // Préparer les données de base pour l'utilisateur inscrit
        $registeredUserData = [
            'role' => $request->input('role'),
            'password' => Hash::make($request->input('password')),
            'balance' => 0,
            'status' => 'active',
            'photo' => $profilePhotoPath ? '/storage/' . $profilePhotoPath : null,
        ];

        // Ajouter les champs spécifiques aux organisateurs
        if ($request->input('role') === 'organizer') {
            $registeredUserData['organization_name'] = $request->input('organization_name');
            $registeredUserData['organization_type'] = $request->input('organization_type');
        }

        // Créer l'utilisateur inscrit
        $registeredUser = new RegisteredUser($registeredUserData);
        $registeredUser->save();

        // Associer les catégories si c'est un organisateur
        if ($request->input('role') === 'organizer' && $request->has('event_types')) {
            $categories = Category::whereIn('id', $request->input('event_types'))->get();
            $registeredUser->categories()->attach($categories);
        }

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

            // Récupérer l'utilisateur enregistré directement
            $registeredUser = RegisteredUser::find($user->userable_id);

            if (!$registeredUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non trouvé'
                ], 404);
            }

            // Informations de base
            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $registeredUser->role,
                'balance' => $registeredUser->balance,
                'status' => $registeredUser->status,
                'photo' => $registeredUser->photo,
            ];

            // Si l'utilisateur est un organisateur, ajouter les informations spécifiques
            if ($registeredUser->role === 'organizer') {
                $userData['organization_name'] = $registeredUser->organization_name;
                $userData['organization_type'] = $registeredUser->organization_type;

                // Charger les catégories avec leurs détails
                $userData['event_types'] = $registeredUser->categories()->select('id', 'label', 'description')->get();
            }

            return response()->json([
                'success' => true,
                'user' => $userData
            ], 200);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json(['success' => false, 'message' => 'Token expiré, veuillez vous reconnecter.'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(['success' => false, 'message' => 'Token invalide.'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['success' => false, 'message' => 'Le token est absent.'], 401);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du profil utilisateur.',
                'error' => $e->getMessage()
            ], 500);
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

            // Récupérer l'utilisateur enregistré directement
            $registeredUser = RegisteredUser::find($user->userable_id);

            if (!$registeredUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non trouvé'
                ], 404);
            }

            // Mettre à jour les informations de base de l'utilisateur
            if ($request->has('name')) {
                $user->name = $request->input('name');
            }

            if ($request->has('phone')) {
                $user->phone = $request->input('phone');
            }

            // Mettre à jour les informations de l'utilisateur enregistré
            if ($request->has('password')) {
                $registeredUser->password = Hash::make($request->input('password'));
            }

            if ($request->has('role')) {
                $registeredUser->role = $request->input('role');
            }

            if ($request->has('status')) {
                $registeredUser->status = $request->input('status');
            }

            if ($request->has('balance')) {
                $registeredUser->balance = $request->input('balance');
            }

            // Gestion de la photo de profil
            if ($request->hasFile('photo')) {
                // Supprimer l'ancienne photo si elle existe
                if ($registeredUser->photo) {
                    Storage::disk('public')->delete($registeredUser->photo);
                }

                // Sauvegarder la nouvelle photo
                $photoPath = $request->file('photo')->store('profile_photos', 'public');
                $registeredUser->photo = $photoPath;
            }

            // Si l'utilisateur est un organisateur, mettre à jour les informations spécifiques
            if ($registeredUser->role === 'organizer') {
                if ($request->has('organization_name')) {
                    $registeredUser->organization_name = $request->input('organization_name');
                }

                if ($request->has('organization_type')) {
                    $registeredUser->organization_type = $request->input('organization_type');
                }

                // Mettre à jour les types d'événements
                if ($request->has('event_types')) {
                    $registeredUser->categories()->sync($request->input('event_types'));
                }
            }

            // Sauvegarder les changements
            $user->save();
            $registeredUser->save();

            // Charger les relations pour la réponse
            $registeredUser->load(['categories', 'user']);

            return response()->json([
                'success' => true,
                'message' => 'Profil mis à jour avec succès',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $registeredUser->role,
                    'status' => $registeredUser->status,
                    'balance' => $registeredUser->balance,
                    'photo' => $registeredUser->photo,
                    'organization_name' => $registeredUser->organization_name,
                    'organization_type' => $registeredUser->organization_type,
                    'categories' => $registeredUser->categories
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du profil',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

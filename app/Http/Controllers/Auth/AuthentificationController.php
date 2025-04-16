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

        // Générer le token JWT avec les claims personnalisés
        $token = JWTAuth::customClaims([
            'iss' => config('app.url'),
            'sub' => $registeredUser->id,
            'role' => $registeredUser->role
        ])->fromUser($registeredUser);

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie',
            'token' => $token
        ], 200);
    }

    public function getUserProfile()
    {
        try {
            // ✅ Extraire l'ID du RegisteredUser à partir du token
            $registeredUserId = JWTAuth::parseToken()->getPayload()->get('sub');

            // ✅ Charger le RegisteredUser avec ses relations
            $registeredUser = RegisteredUser::with(['user', 'categories', 'wallets'])->find($registeredUserId);

            if (!$registeredUser || !$registeredUser->user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non trouvé'
                ], 404);
            }

            $user = $registeredUser->user;

            // ✅ Structuration des données utilisateur
            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $registeredUser->role,
                'balance' => $registeredUser->balance,
                'status' => $registeredUser->status,
                'photo' => $registeredUser->photo ? url($registeredUser->photo) : null,
                'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $user->updated_at->format('Y-m-d H:i:s')
            ];

            if ($registeredUser->role === 'organizer') {
                $userData['organization'] = [
                    'name' => $registeredUser->organization_name,
                    'type' => $registeredUser->organization_type
                ];

                $userData['event_types'] = $registeredUser->categories->map(function ($cat) {
                    return [
                        'id' => $cat->id,
                        'label' => $cat->label,
                        'description' => $cat->description
                    ];
                });
            }

            $userData['wallets'] = $registeredUser->wallets->map(function ($wallet) {
                return [
                    'id' => $wallet->id,
                    'balance' => $wallet->balance,
                    'currency' => $wallet->currency,
                    'created_at' => $wallet->created_at->format('Y-m-d H:i:s')
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $userData
                ]
            ], 200);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token expiré, veuillez vous reconnecter.'
            ], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Le token est absent ou invalide.'
            ], 401);
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
            // 🔐 Authentifier le registered user à partir du token
            $registeredUserId = JWTAuth::parseToken()->getPayload()->get('sub');
            $registeredUser = RegisteredUser::with('user')->find($registeredUserId);

            if (!$registeredUser || !$registeredUser->user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non trouvé'
                ], 404);
            }

            $user = $registeredUser->user;

            // 📝 Mettre à jour les infos du modèle User
            $user->fill($request->only(['name', 'phone']));

            // 🔐 Si l'utilisateur veut changer son mot de passe
            if ($request->filled('password')) {
                $registeredUser->password = Hash::make($request->input('password'));
            }

            // 🎭 Rôle, statut, solde
            $registeredUser->fill($request->only(['role', 'status', 'balance']));

            // 📸 Gérer la photo de profil
            if ($request->hasFile('photo')) {
                if ($registeredUser->photo) {
                    Storage::disk('public')->delete($registeredUser->photo);
                }

                $photoPath = $request->file('photo')->store('profile_photos', 'public');
                $registeredUser->photo = '/storage/' . $photoPath;
            }

            // 🏢 Si c'est un organisateur, mettre à jour les infos spécifiques
            if ($registeredUser->role === 'organizer') {
                $registeredUser->organization_name = $request->input('organization_name');
                $registeredUser->organization_type = $request->input('organization_type');

                if ($request->has('event_types')) {
                    $registeredUser->categories()->sync($request->input('event_types'));
                }
            }

            // ✅ Sauvegarder les deux modèles
            $user->save();
            $registeredUser->save();

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

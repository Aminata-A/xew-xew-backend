<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use App\Models\AnonymousUser;
use App\Models\RegisteredUser;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AuthentificationController extends Controller
{
    public function register(Request $request)
    {
        $jwtSecret = config('jwt.secret');

        // Récupérer le token JWT de l'en-tête de la requête
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'error' => config('messages.authentification.errors.token.missing')
            ], 400);
        }

        try {
            // Décoder le token pour récupérer l'email
            $decodedToken = JWT::decode($token, new Key($jwtSecret, 'HS256'));
            $email = $decodedToken->email;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => config('messages.authentification.errors.token.invalid')
            ], 401);
        }

        // Validation manuelle des champs
        $errors = [];

        if (empty($request->input('name'))) {
            $errors['name'] = config('messages.authentification.errors.validation.name.required');
        } elseif (strlen($request->input('name')) > 100) {
            $errors['name'] = config('messages.authentification.errors.validation.name.max');
        }

        if (empty($request->input('password'))) {
            $errors['password'] = config('messages.authentification.errors.validation.password.required');
        } elseif (strlen($request->input('password')) < 6) {
            $errors['password'] = config('messages.authentification.errors.validation.password.min');
        } elseif ($request->input('password') !== $request->input('password_confirmation')) {
            $errors['password_confirmation'] = config('messages.authentification.errors.validation.password.confirmation');
        }

        if (empty($request->input('phone'))) {
            $errors['phone'] = config('messages.authentification.errors.validation.phone.required');
        } elseif (strlen($request->input('phone')) > 20) {
            $errors['phone'] = config('messages.authentification.errors.validation.phone.max');
        } else if (!preg_match('/^[0-9]+$/', $request->input('phone'))) {
            $errors['phone'] = config('messages.authentification.errors.validation.phone.numeric');
        }

        if (empty($request->input('role'))) {
            $errors['role'] = config('messages.authentification.errors.validation.role.required');
        } elseif (!in_array($request->input('role'), ['organizer', 'participant'])) {
            $errors['role'] = config('messages.authentification.errors.validation.role.invalid');
        }

        // Vérifier s'il y a des erreurs de validation
        if (!empty($errors)) {
            return response()->json([
                'success' => false,
                'errors' => $errors
            ], 422);
        }

        // Vérifier si l'utilisateur avec cet email existe déjà
        $user = User::where('email', $email)->first();

        if ($user) {
            return response()->json([
                'success' => false,
                'error' => config('messages.authentification.errors.validation.email.already_exists')
            ], 400);
        }

        try {
            // Créer l'utilisateur inscrit
            $registeredUser = RegisteredUser::create([
                'role' => $request->input('role'),
                'password' => Hash::make($request->input('password')),
                'solde' => 0,
                'status' => 'active'
            ]);

            // Créer l'utilisateur de base
            $user = User::create([
                'name' => $request->input('name'),
                'email' => $email,
                'phone' => $request->input('phone'),
                'userable_id' => $registeredUser->id,
                'userable_type' => RegisteredUser::class
            ]);

            return response()->json([
                'success' => true,
                'message' => config('messages.authentification.success.registration'),
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $registeredUser->role
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => config('messages.authentification.errors.general.unexpected')
            ], 500);
        }
    }

    public function login(Request $request)
    {
        // Validation manuelle des champs
        $errors = [];

        if (empty($request->input('email'))) {
            $errors['email'] = config('messages.authentification.errors.validation.email.required');
        } elseif (!filter_var($request->input('email'), FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = config('messages.authentification.errors.validation.email.invalid');
        }

        if (empty($request->input('password'))) {
            $errors['password'] = config('messages.authentification.errors.validation.password.required');
        } elseif (strlen($request->input('password')) < 8) {
            $errors['password'] = config('messages.authentification.errors.validation.password.min');
        }

        // Vérifier s'il y a des erreurs de validation
        if (!empty($errors)) {
            return response()->json([
                'success' => false,
                'errors' => $errors
            ], 422);
        }

        $email = $request->input('email');
        $password = $request->input('password');

        // Récupérer l'utilisateur avec cet email
        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => config('messages.authentification.errors.validation.email.exists')
            ], 401);
        }

        // Vérifier si l'utilisateur est un AnonymousUser
        if ($user->userable instanceof AnonymousUser) {
            return response()->json([
                'success' => false,
                'error' => config('messages.authentification.errors.account.not_activated')
            ], 401);
        }

        // Vérifier le mot de passe
        $registeredUser = $user->userable;

        if (!Hash::check($password, $registeredUser->password)) {
            return response()->json([
                'success' => false,
                'error' => config('messages.authentification.errors.validation.password.invalid')
            ], 401);
        }

        // Vérifier si le compte est actif
        if ($registeredUser->status !== 'active') {
            return response()->json([
                'success' => false,
                'error' => config('messages.authentification.errors.account.inactive')
            ], 401);
        }

        try {
            // Générer le token JWT
            $token = JWTAuth::fromUser($registeredUser);

            return response()->json([
                'success' => true,
                'message' => config('messages.authentification.success.login'),
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $registeredUser->role
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => config('messages.authentification.errors.general.unexpected')
            ], 500);
        }
    }

    public function getUserProfile(Request $request)
    {
        try {
            // Vérifier si un token JWT est présent et authentifier l'utilisateur
            if (! $user = JWTAuth::parseToken()->authenticate()) {
                return response()->json([
                    'success' => false,
                    'error' => config('messages.authentification.errors.token.not_authenticated')
                ], 401);
            }

            // Récupérer les informations utilisateur
            $userProfile = $user->userable;

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $userProfile->role,
                    'balance' => $userProfile->solde,
                    'status' => $userProfile->status,
                    'photo' => $user->photo
                ]
            ], 200);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json([
                'success' => false,
                'error' => config('messages.authentification.errors.token.session_expired')
            ], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json([
                'success' => false,
                'error' => config('messages.authentification.errors.token.session_invalid')
            ], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json([
                'success' => false,
                'error' => config('messages.authentification.errors.token.not_authenticated')
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => config('messages.authentification.errors.general.unexpected')
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            JWTAuth::invalidate($request->bearerToken());
            return response()->json([
                'success' => true,
                'message' => config('messages.authentification.success.logout')
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => config('messages.authentification.errors.general.unexpected')
            ], 500);
        }
    }

    public function updateProfile(Request $request)
    {
        try {
            // Authentification de l'utilisateur
            $user = JWTAuth::parseToken()->authenticate();
            $registeredUser = $user->userable;

            // Validation manuelle des champs
            $errors = [];

            if ($request->has('name') && strlen($request->input('name')) > 100) {
                $errors['name'] = config('messages.authentification.errors.validation.name.max');
            }

            if ($request->has('phone')) {
                if (strlen($request->input('phone')) > 20) {
                    $errors['phone'] = config('messages.authentification.errors.validation.phone.max');
                } else if (!preg_match('/^[0-9]+$/', $request->input('phone'))) {
                    $errors['phone'] = config('messages.authentification.errors.validation.phone.numeric');
                }
            }

            if ($request->has('role') && !in_array($request->input('role'), ['organizer', 'participant'])) {
                $errors['role'] = config('messages.authentification.errors.validation.role.invalid');
            }

            if ($request->hasFile('photo')) {
                $allowedTypes = ['jpeg', 'png', 'jpg', 'gif', 'svg'];
                $extension = $request->file('photo')->getClientOriginalExtension();

                if (!in_array($extension, $allowedTypes)) {
                    $errors['photo'] = config('messages.authentification.errors.validation.photo.invalid_type');
                } elseif ($request->file('photo')->getSize() > 2048 * 1024) {
                    $errors['photo'] = config('messages.authentification.errors.validation.photo.max_size');
                }
            }

            // Vérifier s'il y a des erreurs de validation
            if (!empty($errors)) {
                return response()->json([
                    'success' => false,
                    'errors' => $errors
                ], 422);
            }

            // Mettre à jour les informations de l'utilisateur
            if ($request->has('name')) {
                $user->name = $request->input('name');
            }

            if ($request->has('phone')) {
                $user->phone = $request->input('phone');
            }

            if ($request->has('role')) {
                $registeredUser->role = $request->input('role');
                $registeredUser->save();
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
                'message' => config('messages.authentification.success.profile_update'),
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $registeredUser->role,
                    'photo' => $user->photo
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => config('messages.authentification.errors.general.unexpected')
            ], 500);
        }
    }
}

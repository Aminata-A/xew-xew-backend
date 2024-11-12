<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\StoreWalletRequest;
use App\Http\Requests\UpdateWalletRequest;

class WalletController extends Controller
{
    // Liste des portefeuilles de l'utilisateur connecté
    public function index()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            // Récupérer tous les portefeuilles de l'utilisateur connecté
            $wallets = Wallet::where('user_id', $user->id)->get();

            return response()->json($wallets, 200);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json(['message' => 'Le token a expiré. Veuillez vous reconnecter.'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(['message' => 'Le token est invalide.'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['message' => 'Token absent.'], 401);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur lors de la récupération des portefeuilles.'], 500);
        }
    }

    // Créer un nouveau portefeuille
    public function store(StoreWalletRequest $request)
    {
        try {
            // Authentifier l'utilisateur via JWT
            $user = JWTAuth::parseToken()->authenticate();

            // Validation de la requête
            $validatedData = $request->validated();

            // Forcer la majuscule pour le nom du portefeuille et s'assurer qu'il correspond à l'énumération
            $validatedData['name'] = strtoupper($validatedData['name']);

            // Vérification que le nom est valide
            if (!in_array($validatedData['name'], ['WAVE', 'ORANGE_MONEY', 'FREE_MONEY'])) {
                return response()->json(['message' => 'Nom du portefeuille invalide'], 400);
            }

            // Créer le portefeuille et l'associer à l'utilisateur authentifié
            $wallet = Wallet::create(array_merge($validatedData, ['user_id' => $user->id]));

            return response()->json(['message' => 'Portefeuille créé avec succès', 'wallet' => $wallet], 201);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json(['message' => 'Le token a expiré. Veuillez vous reconnecter.'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(['message' => 'Le token est invalide.'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['message' => 'Token absent.'], 401);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur lors de la création du portefeuille.'], 500);
        }
    }

    // Voir les détails d'un portefeuille
    public function show($id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $wallet = Wallet::where('id', $id)->where('user_id', $user->id)->first();

            if (!$wallet) {
                return response()->json(['message' => 'Portefeuille non trouvé'], 404);
            }

            return response()->json($wallet, 200);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json(['message' => 'Le token a expiré. Veuillez vous reconnecter.'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(['message' => 'Le token est invalide.'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['message' => 'Token absent.'], 401);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur lors de la récupération du portefeuille.'], 500);
        }
    }

    // Mettre à jour un portefeuille
    public function update(UpdateWalletRequest $request, $id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            // Trouver le portefeuille à mettre à jour
            $wallet = Wallet::findOrFail($id);

            // Vérifie si le portefeuille appartient à l'utilisateur authentifié
            if ($wallet->user_id !== $user->id) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            // Valider les données envoyées via la requête
            $validatedData = $request->validated();

            // Mettre à jour les champs du portefeuille
            $wallet->update([
                'name' => strtoupper($validatedData['name']),
                'wallet_number' => $validatedData['wallet_number'],
                'balance' => $validatedData['balance'],
                'identifier' => $validatedData['identifier'], // Met à jour l'identifier
            ]);

            return response()->json(['message' => 'Portefeuille mis à jour avec succès', 'wallet' => $wallet], 200);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json(['message' => 'Le token a expiré. Veuillez vous reconnecter.'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(['message' => 'Le token est invalide.'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['message' => 'Token absent.'], 401);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur lors de la mise à jour du portefeuille.'], 500);
        }
    }

    // Supprimer un portefeuille (soft delete)
    public function destroy($id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $wallet = Wallet::where('id', $id)->where('user_id', $user->id)->first();

            if (!$wallet) {
                return response()->json(['message' => 'Portefeuille non trouvé'], 404);
            }

            $wallet->delete(); // Soft delete

            return response()->json(['message' => 'Portefeuille supprimé avec succès'], 200);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json(['message' => 'Le token a expiré. Veuillez vous reconnecter.'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(['message' => 'Le token est invalide.'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['message' => 'Token absent.'], 401);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur lors de la suppression du portefeuille.'], 500);
        }
    }

    // Restaurer un portefeuille supprimé (restauration)
    public function restore($id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $wallet = Wallet::onlyTrashed()->where('id', $id)->where('user_id', $user->id)->first();

            if (!$wallet) {
                return response()->json(['message' => 'Portefeuille non trouvé ou non supprimé'], 404);
            }

            $wallet->restore(); // Restaurer le portefeuille

            return response()->json(['message' => 'Portefeuille restauré avec succès', 'wallet' => $wallet], 200);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json(['message' => 'Le token a expiré. Veuillez vous reconnecter.'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(['message' => 'Le token est invalide.'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['message' => 'Token absent.'], 401);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur lors de la restauration du portefeuille.'], 500);
        }
    }

    // Lister tous les portefeuilles supprimés
    public function trash()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $wallets = Wallet::onlyTrashed()->where('user_id', $user->id)->get();

            return response()->json($wallets, 200);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json(['message' => 'Le token a expiré. Veuillez vous reconnecter.'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(['message' => 'Le token est invalide.'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['message' => 'Token absent.'], 401);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur lors de la récupération des portefeuilles supprimés.'], 500);
        }
    }

    // Supprimer définitivement un portefeuille
    public function forceDestroy($id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $wallet = Wallet::withTrashed()->where('id', $id)->where('user_id', $user->id)->first();

            if (!$wallet) {
                return response()->json(['message' => 'Portefeuille non trouvé'], 404);
            }

            $wallet->forceDelete(); // Suppression définitive

            return response()->json(['message' => 'Portefeuille supprimé définitivement'], 200);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json(['message' => 'Le token a expiré. Veuillez vous reconnecter.'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(['message' => 'Le token est invalide.'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['message' => 'Token absent.'], 401);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur lors de la suppression définitive du portefeuille.'], 500);
        }
    }
}

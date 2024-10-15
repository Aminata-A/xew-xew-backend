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
        $user = JWTAuth::parseToken()->authenticate();

        // Récupérer tous les portefeuilles de l'utilisateur connecté
        $wallets = Wallet::where('user_id', $user->id)->get();

        return response()->json($wallets, 200);
    }

    // Créer un nouveau portefeuille
    public function store(StoreWalletRequest $request)
    {
        // Authenticate the user via JWT
        $user = JWTAuth::parseToken()->authenticate();

        // Check if authentication failed
        if (!$user) {
            return response()->json(['message' => 'Non autorisé'], 401);
        }

        // Validation de la requête
        $validatedData = $request->validated();

        // Forcer la majuscule pour le nom du portefeuille et s'assurer qu'il correspond à l'énumération
        $validatedData['name'] = strtoupper($validatedData['name']);

        // Vérification que le nom est valide (optionnelle mais utile)
        if (!in_array($validatedData['name'], ['WAVE', 'ORANGE_MONEY', 'FREE_MONEY'])) {
            return response()->json(['message' => 'Nom du portefeuille invalide'], 400);
        }

        // Créer le portefeuille et l'associer à l'utilisateur authentifié
        $wallet = Wallet::create(array_merge($validatedData, ['user_id' => $user->id]));

        return response()->json(['message' => 'Portefeuille créé avec succès', 'wallet' => $wallet], 201);
    }





    // Voir les détails d'un portefeuille
    public function show($id)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $wallet = Wallet::where('id', $id)->where('user_id', $user->id)->first();

        if (!$wallet) {
            return response()->json(['message' => 'Portefeuille non trouvé'], 404);
        }

        return response()->json($wallet, 200);
    }

    // Mettre à jour un portefeuille
    public function update(UpdateWalletRequest $request, $id)
    {
        $user = JWTAuth::parseToken()->authenticate();

        if (!Auth::check()) {
            return response()->json(['message' => 'Non autorisé'], 401);
        }

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
            'name' => $validatedData['name'],
            'wallet_number' => $validatedData['wallet_number'],
            'balance' => $validatedData['balance'],
            'identifier' => $validatedData['identifier'], // Met à jour l'identifier
        ]);

        return response()->json([
            'message' => 'Portefeuille mis à jour avec succès',
            'wallet' => $wallet
        ], 200);
    }


    // Supprimer un portefeuille (soft delete)
    public function destroy($id)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $wallet = Wallet::where('id', $id)->where('user_id', $user->id)->first();

        if (!$wallet) {
            return response()->json(['message' => 'Portefeuille non trouvé'], 404);
        }

        $wallet->delete(); // Soft delete

        return response()->json(['message' => 'Portefeuille supprimé avec succès'], 200);
    }

    // Récupérer un portefeuille supprimé (restauration)
    public function restore($id)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $wallet = Wallet::onlyTrashed()->where('id', $id)->where('user_id', $user->id)->first();

        if (!$wallet) {
            return response()->json(['message' => 'Portefeuille non trouvé ou non supprimé'], 404);
        }

        $wallet->restore(); // Restaurer le portefeuille

        return response()->json(['message' => 'Portefeuille restauré avec succès', 'wallet' => $wallet], 200);
    }

    // Lister tous les portefeuilles supprimés
    public function trash()
    {
        $user = JWTAuth::parseToken()->authenticate();
        $wallets = Wallet::onlyTrashed()->where('user_id', $user->id)->get();

        return response()->json($wallets, 200);
    }

    // Supprimer définitivement un portefeuille
    public function forceDestroy($id)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $wallet = Wallet::withTrashed()->where('id', $id)->where('user_id', $user->id)->first();

        if (!$wallet) {
            return response()->json(['message' => 'Portefeuille non trouvé'], 404);
        }

        $wallet->forceDelete(); // Suppression définitive

        return response()->json(['message' => 'Portefeuille supprimé définitivement'], 200);
    }
}

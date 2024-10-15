<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use Exception;

class EventController extends Controller
{
    // Lister tous les événements
    public function index()
    {
        try {
            // Récupérer tous les événements non soft-supprimés (sans deleted_at)
            $events = Event::whereNull('deleted_at')
                ->where('event_status', '!=', 'supprimer')  // Exclure les événements avec le statut 'supprimer'
                ->get();

            // Retourner les événements en JSON
            return response()->json($events, 200);
        } catch (Exception $e) {
            // En cas d'erreur, retourner un message d'erreur avec le code 500
            return response()->json(['error' => 'Erreur lors de la récupération des événements', 'details' => $e->getMessage()], 500);
        }
    }

    // Créer un nouvel événement
    public function store(StoreEventRequest $request)
    {
        try {
            // Authentifier l'utilisateur
            $user = JWTAuth::user();
            if (!$user) {
                // Si l'utilisateur n'est pas authentifié, retourner un code 401 (non autorisé)
                return response()->json(['message' => 'Non autorisé'], 401);
            }

            // Récupérer les données de la requête JSON
            $data = $request->json()->all();
            // Créer un nouvel événement avec les données fournies et l'ID de l'organisateur (utilisateur connecté)
            $event = Event::create(array_merge($data, ['organizer_id' => $user->id]));

            // Si des catégories sont envoyées avec la requête, synchroniser les catégories avec l'événement
            if (isset($data['categories'])) {
                $event->categories()->sync($data['categories']);
            }

            // Si des portefeuilles sont envoyés, synchroniser les portefeuilles avec l'événement
            if (isset($data['wallets'])) {
                $event->wallets()->sync($data['wallets']);
            }

            // Retourner un message de succès avec l'événement créé, code 201 (créé avec succès)
            return response()->json(['message' => 'Événement créé avec succès', 'event' => $event], 201);
        } catch (Exception $e) {
            // En cas d'erreur, retourner un message d'erreur avec les détails
            return response()->json(['error' => 'Erreur lors de la création de l\'événement', 'details' => $e->getMessage()], 500);
        }
    }

    // Voir les détails d'un événement spécifique
    public function show($id)
    {
        try {
            // Chercher l'événement par son ID, y compris ceux soft-supprimés
            $event = Event::with('categories')->withTrashed()->find($id);
            if (!$event) {
                // Si l'événement n'est pas trouvé, retourner une réponse avec code 404
                return response()->json(['erreur' => 'Événement non trouvé'], 404);
            }

            // Retourner les détails de l'événement
            return response()->json($event, 200);
        } catch (Exception $e) {
            // En cas d'erreur, retourner un message d'erreur
            return response()->json(['error' => 'Erreur lors de la récupération de l\'événement', 'details' => $e->getMessage()], 500);
        }
    }

    // Lister tous les événements créés par l'utilisateur connecté
    public function myEvents()
    {
        try {
            // Authentifier l'utilisateur via JWT
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                // Si l'utilisateur n'est pas authentifié, retourner une erreur
                return response()->json(['message' => 'Non autorisé'], 401);
            }

            // Récupérer tous les événements dont l'organisateur est l'utilisateur connecté
            $events = Event::where('organizer_id', $user->id)
                ->whereNull('deleted_at')  // Exclure les événements soft-supprimés
                ->where('event_status', '!=', 'supprimer')  // Exclure les événements supprimés
                ->get();

            // Retourner les événements en JSON
            return response()->json($events, 200);
        } catch (Exception $e) {
            // Gérer les erreurs et retourner un message d'erreur
            return response()->json(['error' => 'Erreur lors de la récupération des événements de l\'utilisateur', 'details' => $e->getMessage()], 500);
        }
    }

    // Modifier un événement
    public function update(UpdateEventRequest $request, $id)
    {
        try {
            // Authentifier l'utilisateur
            $user = Auth::user();
            if (!$user) {
                // Si l'utilisateur n'est pas authentifié, retourner une erreur
                return response()->json(['message' => 'Non autorisé'], 401);
            }

            // Récupérer l'événement par son ID
            $event = Event::find($id);
            if (!$event) {
                // Si l'événement n'est pas trouvé, retourner une erreur
                return response()->json(['message' => 'Événement non trouvé'], 404);
            }

            // Récupérer les données de la requête
            $data = $request->json()->all();
            // Mettre à jour l'événement avec les nouvelles données
            $event->update(array_merge($data, ['organizer_id' => $user->id]));

            // Gérer les catégories et portefeuilles si présents
            if (isset($data['categories'])) {
                $event->categories()->sync($data['categories']);
            }
            if (isset($data['wallets'])) {
                $event->wallets()->sync($data['wallets']);
            }

            // Retourner un message de succès
            return response()->json(['message' => 'Événement mis à jour avec succès', 'event' => $event], 200);
        } catch (Exception $e) {
            // Gérer les erreurs et retourner un message d'erreur
            return response()->json(['error' => 'Erreur lors de la mise à jour de l\'événement', 'details' => $e->getMessage()], 500);
        }
    }

    // Supprimer un événement
    public function destroy($id)
    {
        try {
            // Authentifier l'utilisateur
            $user = JWTAuth::parseToken()->authenticate();
            // Trouver l'événement par son ID
            $event = Event::findOrFail($id);

            // Vérifier si l'utilisateur connecté est l'organisateur
            if ($event->organizer_id != $user->id) {
                // Si non, retourner une erreur 403 (non autorisé)
                return response()->json(['erreur' => 'Non autorisé'], 403);
            }

            // Effectuer un soft delete de l'événement
            $event->delete();

            // Retourner un message de succès
            return response()->json(['message' => 'Événement supprimé avec succès'], 200);
        } catch (Exception $e) {
            // Gérer les erreurs et retourner un message d'erreur
            return response()->json(['error' => 'Erreur lors de la suppression de l\'événement', 'details' => $e->getMessage()], 500);
        }
    }

    // Restaurer un événement soft-supprimé
    public function restore($id)
    {
        try {
            // Authentifier l'utilisateur
            $user = JWTAuth::parseToken()->authenticate();
            // Trouver l'événement dans ceux soft-supprimés
            $event = Event::onlyTrashed()->where('id', $id)->first();

            // Vérifier si l'événement existe
            if (!$event) {
                return response()->json(['erreur' => 'Événement non trouvé'], 404);
            }

            // Vérifier que l'utilisateur connecté est l'organisateur
            if ($event->organizer_id != $user->id) {
                return response()->json(['erreur' => 'Non autorisé'], 403);
            }

            // Restaurer l'événement
            $event->restore();

            // Retourner un message de succès
            return response()->json(['message' => 'Événement restauré avec succès', 'event' => $event], 200);
        } catch (Exception $e) {
            // Gérer les erreurs et retourner un message d'erreur
            return response()->json(['error' => 'Erreur lors de la restauration de l\'événement', 'details' => $e->getMessage()], 500);
        }
    }

    // Lister tous les événements supprimés
    public function trash()
    {
        try {
            // Authentifier l'utilisateur
            $user = JWTAuth::parseToken()->authenticate();
            // Récupérer les événements soft-supprimés créés par l'utilisateur
            $events = Event::onlyTrashed()->where('organizer_id', $user->id)->get();

            // Retourner les événements en JSON
            return response()->json($events, 200);
        } catch (Exception $e) {
            // Gérer les erreurs et retourner un message d'erreur
            return response()->json(['error' => 'Erreur lors de la récupération des événements supprimés', 'details' => $e->getMessage()], 500);
        }
    }

    // Suppression définitive d'un événement
    public function forceDestroy($id)
    {
        try {
            // Authentifier l'utilisateur
            $user = JWTAuth::parseToken()->authenticate();
            // Trouver l'événement, y compris ceux soft-supprimés
            $event = Event::withTrashed()->where('id', $id)->first();

            // Vérifier si l'événement existe
            if (!$event) {
                return response()->json(['erreur' => 'Événement non trouvé'], 404);
            }

            // Vérifier que l'utilisateur connecté est l'organisateur
            if ($event->organizer_id != $user->id) {
                return response()->json(['erreur' => 'Non autorisé'], 403);
            }

            // Effectuer une suppression définitive de l'événement
            $event->forceDelete();

            // Retourner un message de succès
            return response()->json(['message' => 'Événement supprimé définitivement avec succès'], 200);
        } catch (Exception $e) {
            // Gérer les erreurs et retourner un message d'erreur
            return response()->json(['error' => 'Erreur lors de la suppression définitive de l\'événement', 'details' => $e->getMessage()], 500);
        }
    }
}

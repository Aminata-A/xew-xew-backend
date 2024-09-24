<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;


class EventController extends Controller
{


    // Lister tous les événements
    public function index()
    {
        // Récupérer tous les événements qui ne sont pas supprimés de manière soft (pas de deleted_at)
        $events = Event::whereNull('deleted_at')
        ->where('event_status', '!=', 'supprimer')
        ->get();

        return response()->json($events);
    }

    // Créer un nouvel événement
    // Créer un nouvel événement
    public function store(StoreEventRequest $request)
{
    // Authentifier l'utilisateur
    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Non autorisé'], 401);
    }

    // Récupérer les données de la requête JSON
    $data = $request->json()->all();

    // Créer un nouvel événement en ajoutant l'ID de l'organisateur
    $event = Event::create(array_merge($data, ['organizer_id' => $user->id]));

    // Gérer les catégories si elles sont présentes dans la requête
    if (isset($data['categories'])) {
        $event->categories()->sync($data['categories']);
    }

    return response()->json(['message' => 'Événement créé avec succès', 'event' => $event], 201);
}




    // Voir les détails d'un événement
    public function show($id)
    {
        $event = Event::find($id);

        if (!$event) {
            return response()->json(['erreur' => 'Événement non trouvé'], 404);
        }

        return response()->json($event);
    }

    // Modifier un événement (uniquement si c'est le créateur)
    // Modifier un événement (uniquement si c'est le créateur)
    public function update(StoreEventRequest $request, $id)
    {
        // Authentifier l'utilisateur
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Non autorisé'], 401);
        }

        // Récupérer les données de la requête JSON
        $data = $request->json()->all();

        // Trouver l'événement par ID
        $event = Event::find($id);
        if (!$event) {
            return response()->json(['message' => 'Événement non trouvé'], 404);
        }

        // Mettre à jour l'événement avec les nouvelles données
        $event->update(array_merge($data, ['organizer_id' => $user->id]));

        // Gérer les catégories si elles sont présentes dans la requête
        if (isset($data['categories'])) {
            $event->categories()->sync($data['categories']);
        }

        return response()->json(['message' => 'Événement mis à jour avec succès', 'event' => $event], 200);
    }




    // Supprimer un événement (uniquement si c'est le créateur)
    public function destroy($id)
    {
        $user = JWTAuth::parseToken()->authenticate();

        // Chercher l'événement par son ID
        $event = Event::findOrFail($id);

        // Vérifier si l'événement existe
        if (!$event) {
            return response()->json(['erreur' => 'Événement non trouvé'], 404);
        }

        // Vérifier que l'utilisateur connecté est bien l'organisateur de l'événement
        if ($event->organizer_id != $user->id) {
            return response()->json(['erreur' => "Vous n'êtes pas autorisé à supprimer cet événement"], 403);
        }

        // Soft delete de l'événement (suppression logique)
        $event->delete();

        return response()->json(['message' => 'Événement supprimé avec succès'], 200);
    }



    // faire un softdelete
    public function restore($id)
    {
        $user = JWTAuth::parseToken()->authenticate();

        // Chercher l'événement dans la table avec les éléments supprimés
        $event = Event::onlyTrashed()->where('id', $id)->first();

        // Vérifier si l'événement existe
        if (!$event) {
            return response()->json(['erreur' => 'Événement non trouvé ou non supprimé'], 404);
        }

        // Vérifier que l'utilisateur connecté est bien l'organisateur de l'événement
        if ($event->organizer_id != $user->id) {
            return response()->json(['erreur' => "Vous n'êtes pas autorisé à restaurer cet événement"], 403);
        }

        // Récupérer (restaurer) l'événement supprimé
        $event->restore();

        return response()->json(['message' => 'Événement restauré avec succès', 'event' => $event], 200);
    }



    // Lister tous les événements supprimés
    public function trash()
    {
        $user = JWTAuth::parseToken()->authenticate();

        // Récupérer tous les événements supprimés créés par l'utilisateur connecté
        $events = Event::onlyTrashed()->where('organizer_id', $user->id)->get();

        return response()->json($events);
    }


    // forcement supprimer un événement
    public function forceDestroy($id)
    {
        $user = JWTAuth::parseToken()->authenticate();

        // Chercher l'événement, y compris ceux qui sont supprimés de manière soft
        $event = Event::withTrashed()->where('id', $id)->first();

        // Vérifier si l'événement existe
        if (!$event) {
            return response()->json(['erreur' => 'Événement non trouvé'], 404);
        }

        // Vérifier que l'utilisateur connecté est bien l'organisateur de l'événement
        if ($event->organizer_id != $user->id) {
            return response()->json(['erreur' => "Vous n'êtes pas autorisé à supprimer définitivement cet événement"], 403);
        }

        // Supprimer définitivement l'événement
        $event->forceDelete();

        return response()->json(['message' => 'Événement supprimé définitivement avec succès'], 200);
    }

}

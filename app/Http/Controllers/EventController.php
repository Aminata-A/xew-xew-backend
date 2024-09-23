<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;


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
    public function store(Request $request)
    {

        // Validation des champs
        // $validatedData = $request->validate([
        //     'name' => 'required|string|max:255',
        //     'description' => 'required|string',
        //     'date' => 'required|date_format:d/m/Y',
        //     'time' => 'required|date_format:H:i',
        //     'banner' => 'required|url',
        //     'location' => 'required|string|max:255',
        //     'ticket_quantity' => 'required|integer',
        //     'ticket_price' => 'required|numeric',
        //     'event_status' => 'required|string',
        //     'categories' => 'required|array', // categories must be an array
        //     'categories.*' => 'exists:categories,id' // each category must exist in the categories table
        // ]);

        // // Début de la transaction
        // DB::beginTransaction();

        try {
            // Créer un nouvel événement
            $event = Event::create($request->all());

            // Associer les catégories via la table pivot
            $event->categories()->sync($request->categories);

            // Commit de la transaction si tout est OK
            DB::commit();

            return response()->json([
                'message' => 'Événement créé avec succès',
                'event' => $event
            ], 201);

        } catch (\Exception $e) {
            echo $e;
            // Rollback en cas d'erreur
            DB::rollBack();
            return response()->json(['error' => 'Erreur lors de la création de l\'événement'], 500);
        };
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
    public function update(Request $request, $id)
{
    // Get the authenticated user
    $user = Auth::user();

    // Find the event by its ID
    $event = Event::find($id );

    // Check if the event exists
    if (!$event) {
        // dd($event);
        return response()->json(['erreur' => 'Événement non trouvé'], 404);
    }

    // Check if the authenticated user is the organizer of the event
    if ($event->organizer_id != 1) {
        return response()->json(['erreur' => "Vous n'êtes pas autorisé à modifier cet événement"], 403);
    }

    // // Validate the request data
    // $validatedData = $request->validate([
    //     'name' => 'sometimes|required|string|max:255',
    //     'date' => 'sometimes|required|date',
    //     'time' => 'sometimes|required',
    //     'location' => 'sometimes|required|string|max:255',
    //     'event_status' => 'sometimes|required|in:publier,brouillon,archiver,annuler,supprimer',
    //     'description' => 'nullable|string',
    //     'banner' => 'nullable|string',
    //     'ticket_quantity' => 'sometimes|required|integer|min:1',
    //     'ticket_price' => 'sometimes|required|numeric|min:0',
    //     'categories' => 'sometimes|array',
    //     'categories.*' => 'exists:categories,id' // Ensure that each category exists
    // ]);

    // Update the event with the validated data
    $event->update($request->all());

    // Sync categories (replaces existing ones with new ones if provided)
    if ($request->has('categories')) {
        $event->categories()->sync($request->categories);
    }

    return response()->json(['message' => 'Événement mis à jour avec succès', 'event' => $event], 200);
}


    // Supprimer un événement (uniquement si c'est le créateur)
    public function destroy($id)
    {
        // $user = Auth::user();
        $event = Event::find($id);

        if (!$event) {
            return response()->json(['erreur' => 'Événement non trouvé'], 404);
        }

        // Vérifier que l'utilisateur connecté est bien le créateur de l'événement
        // $user->id == $event->organizer_id
        if ($event->organizer_id != 1) {
            return response()->json(['erreur' => "Vous n'êtes pas autorisé à supprimer cet événement"], 403);
        }

        // Soft delete the event
        $event->delete();

        return response()->json(['message' => 'Événement supprimé avec succès'], 200);
    }


    // faire un softdelete
    public function restore($id)
    {
        // Chercher l'événement dans la table avec les éléments supprimés
        $event = Event::onlyTrashed()->where('id', $id)->first();

        // Vérifier si l'événement existe
        if (!$event) {
            return response()->json(['erreur' => 'Événement non trouvé ou non supprimé'], 404);
        }

        // Récupérer (restaurer) l'événement supprimé
        $event->restore();

        return response()->json(['message' => 'Événement restauré avec succès', 'event' => $event], 200);
    }


    // Lister tous les événements supprimés
    public function trash()
    {
        $events = Event::onlyTrashed()->get();
        return response()->json($events);
    }

    // forcement supprimer un événement
    public function forceDestroy($id)
    {
        $event = Event::withTrashed()->where('id', $id)->first();
        $event->forceDelete();
        return response()->json(['message' => 'Événement supprimé avec succès', 'event' => $event], 200);
    }
}

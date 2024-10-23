<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Event;
use Illuminate\Http\Request;
use App\Models\CategoryEvent;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class EventController extends Controller
{
    // Lister tous les événements
    public function index(Request $request)
    {
        try {
            // Récupérer la catégorie à filtrer si présente dans la requête
            $categoryId = $request->query('category_id');

            // Construire la requête de base
            $query = Event::with(['organizer.user', 'categories'])
                ->whereNull('deleted_at')
                ->where('event_status', '!=', 'supprimer');

            // Si une catégorie est spécifiée, ajouter une condition à la requête
            if ($categoryId) {
                $query->whereHas('categories', function ($q) use ($categoryId) {
                    $q->where('categories.id', $categoryId);
                });
            }

            // Récupérer les événements en fonction des filtres appliqués
            $events = $query->get();

            return response()->json($events, 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Erreur lors de la récupération des événements', 'details' => $e->getMessage()], 500);
        }
    }


    // Créer un nouvel événement avec validation et messages d'erreur personnalisés    // Créer un nouvel événement avec validation et messages d'erreur personnalisés
    public function store(Request $request)
    {
        try {
            // Authenticate user
            $user = JWTAuth::user();
            if (!$user) {
                return response()->json(['message' => 'Non autorisé'], 401);
            }

            $data = json_decode($request->input('body'), true);
            // Validate event data
            $validator = Validator::make($data, [
                'name' => 'required|string|max:255',
                'date' => 'sometimes|date|after:today',  // Vérifier que la date est dans le futur
                'time' => 'required',
                'location' => 'required|string|max:255',
                'description' => 'required|string',
                'ticket_price' => 'required|numeric|min:0',  // Prix ne doit pas être négatif
                'ticket_quantity' => 'required|integer|min:0',  // Quantité ne doit pas être négative
            ], [
                'name.required' => 'Le nom de l\'événement est obligatoire.',
                'name.string' => 'Le nom de l\'événement doit être une chaîne de caractères.',
                'name.max' => 'Le nom de l\'événement ne peut pas dépasser 255 caractères.',
                'date.required' => 'La date de l\'événement est obligatoire.',
                'date.date' => 'La date de l\'événement doit être une date valide.',
                'time.required' => 'L\'heure de l\'événement est obligatoire.',
                'location.required' => 'Le lieu de l\'événement est obligatoire.',
                'location.string' => 'Le lieu de l\'événement doit être une chaîne de caractères.',
                'location.max' => 'Le lieu de l\'événement ne peut pas dépasser 255 caractères.',
                'ticket_price.required' => 'Le prix du billet est obligatoire.',
                'ticket_price.numeric' => 'Le prix du billet doit être un nombre.',
                'ticket_quantity.required' => 'Le nombre de billets est obligatoire.',
                'ticket_quantity.integer' => 'Le nombre de billets doit être un entier.',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $validatedData = $validator->validated();

            // Validate banner file
            $banner = $request->file('banner');
            if (!$banner) {
                return response()->json(['error' => 'La bannière est obligatoire'], 400);
            }

            $bannerValidator = Validator::make(['banner' => $banner], [
                'banner' => 'required|file|mimes:jpeg,png,jpg,gif|max:2048',
            ], [
                'banner.required' => 'La bannière est obligatoire.',
                'banner.file' => 'Le fichier doit être un fichier valide.',
                'banner.mimes' => 'Le fichier doit être un type de fichier : jpeg, png, jpg ou gif.',
                'banner.max' => 'Le fichier ne doit pas dépasser 2 Mo.',
            ]);

            if ($bannerValidator->fails()) {
                return response()->json(['errors' => $bannerValidator->errors()], 422);
            }

            // Store banner file
            $filename = 'banner-' . $validatedData['name'] . '.' . $banner->getClientOriginalExtension();
            $filePath = $banner->storeAs('public/events', $filename, 'public');
            $validatedData['banner'] = '/storage/' . $filePath;

            // Create the event
            $event = Event::create(array_merge($validatedData, ['organizer_id' => $user->id]));
            // TODO ADD CATEGORIES AND WALLETS AND THEN SYNC THEM
    // Gérer les catégories si elles sont présentes dans la requête
    if (isset($data['categories'])) {

        $event->categories()->sync($data['categories']);  // Sync multiple categories
        
    }
    // Gérer les portefeuilles si ils sont présents dans la requête (table pivot EventPaymentMethod)
    if (isset($data['wallets'])) {
        $event->wallets()->sync($data['wallets']);  // Sync multiple wallets
    }
            return response()->json(['message' => 'Événement créé avec succès', 'event' => $event], 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Erreur lors de la création de l\'événement', 'details' => $e->getMessage()], 500);
        }
    }

//     public function store(Request $request)
// {
//     try {
//         $user = JWTAuth::user();
//         if (!$user) {
//             return response()->json(['message' => 'Non autorisé'], 401);
//         }

//         $data = json_decode($request->input('body'), true);

//         DB::beginTransaction();

//         // Validation des données
//         $validator = Validator::make($data, [
//             'name' => 'required|string|max:255',
//             'date' => 'sometimes|date|after:today',
//             'time' => 'required',
//             'location' => 'required|string|max:255',
//             'description' => 'required|string',
//             'ticket_price' => 'required|numeric|min:0',
//             'ticket_quantity' => 'required|integer|min:30',
//             'categories' => 'required|array', // Array of category IDs
//             'categories.*' => 'exists:categories,id', // Ensure categories exist
//         ]);

//         if ($validator->fails()) {
//             return response()->json(['errors' => $validator->errors()], 422);
//         }

//         $validatedData = $validator->validated();

//         // Create the event
//         $event = Event::create(array_merge($validatedData, ['organizer_id' => $user->id]));

//         // create categorie
//         foreach ($request->categories as $index => $categoryId) {
//             CategoryEvent::create([
//                 'event_id' => $event->id,
//                 'category_id' => $categoryId
//             ]);
//         }

//         // Sync the event with the provided categories
//         $event->load('categories');

//         DB::commit();


//         return response()->json(['message' => 'Événement créé avec succès', 'event' => $event], 201);
//     } catch (Exception $e) {
//         return response()->json(['error' => 'Erreur lors de la création de l\'événement', 'details' => $e->getMessage()], 500);
//     }
// }






    // Voir les détails d'un événement spécifique
    public function show($id)
    {
        try {
            $event = Event::with(['organizer', 'organizer.user', 'categories', 'wallets'])
                ->withTrashed()
                ->find($id);

            if (!$event) {
                return response()->json(['erreur' => 'Événement non trouvé'], 404);
            }

            return response()->json($event, 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Erreur lors de la récupération de l\'événement', 'details' => $e->getMessage()], 500);
        }
    }

    // Lister tous les événements créés par l'utilisateur connecté
    public function myEvents()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json(['message' => 'Non autorisé'], 401);
            }

            $events = Event::with('organizer', 'organizer.user')
                ->where('organizer_id', $user->id)
                ->whereNull('deleted_at')
                ->where('event_status', '!=', 'supprimer')
                ->get();

            return response()->json($events, 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Erreur lors de la récupération des événements de l\'utilisateur', 'details' => $e->getMessage()], 500);
        }
    }

    // Modifier un événement avec validation et messages personnalisés
    // Modifier un événement avec validation et messages personnalisés
    public function update(Request $request, $id)
    {
        try {
            // Authentifier l'utilisateur
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json(['message' => 'Non autorisé'], 401);
            }

            // Récupérer l'événement par son ID
            $event = Event::find($id);
            if (!$event) {
                return response()->json(['message' => 'Événement non trouvé'], 404);
            }

            // Vérifier si l'utilisateur connecté est l'organisateur
            if ($event->organizer_id != $user->id) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            // Validation des données pour la mise à jour avec messages personnalisés
            $validatedData = $request->validate([
                'name' => 'sometimes|string|max:255',
                'date' => 'required|date|after:today',  // Date doit être dans le futur
                'time' => 'sometimes',
                'location' => 'sometimes|string|max:255',
                'ticket_price' => 'required|numeric|min:0',  // Prix ne doit pas être négatif
                'ticket_quantity' => 'required|integer|min:0',  // Quantité ne doit pas être négative
                'banner' => 'sometimes|file|mimes:jpeg,png,jpg,gif|max:2048', // Validation de la bannière
            ], [
                'name.string' => 'Le nom de l\'événement doit être une chaîne de caractères.',
                'name.max' => 'Le nom de l\'événement ne peut pas dépasser 255 caractères.',
                'date.date' => 'La date de l\'événement doit être une date valide.',
                'location.string' => 'Le lieu de l\'événement doit être une chaîne de caractères.',
                'location.max' => 'Le lieu de l\'événement ne peut pas dépasser 255 caractères.',
                'ticket_price.numeric' => 'Le prix du billet doit être un nombre.',
                'ticket_quantity.integer' => 'Le nombre de billets doit être un entier.',
                'banner.file' => 'L\'affiche doit être un fichier.',
                'banner.mimes' => 'L\'affiche doit être au format jpeg, png, jpg ou gif.',
                'banner.max' => 'La taille de l\'affiche ne peut pas dépasser 2 Mo.',
            ]);

            // Gérer l'upload du fichier banner
            if ($request->hasFile('banner')) {
                $file = $request->file('banner');
                $filename = time() . '_' . $file->getClientOriginalName();
                $filePath = $file->storeAs('uploads/events', $filename, 'public');
                $validatedData['banner'] = '/storage/' . $filePath;
            }

            // Mise à jour de l'événement avec les nouvelles données
            $event->update(array_merge($validatedData, ['organizer_id' => $user->id]));

            // Synchronisation des catégories et wallets si présents
            if ($request->has('categories')) {
                $event->categories()->sync($request->input('categories'));
            }
            if ($request->has('wallets')) {
                $event->wallets()->sync($request->input('wallets'));
            }

            return response()->json(['message' => 'Événement mis à jour avec succès', 'event' => $event], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Erreur lors de la mise à jour de l\'événement', 'details' => $e->getMessage()], 500);
        }
    }


    // Supprimer un événement
    public function destroy($id)
    {
        try {
            // Authentifier l'utilisateur
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json(['message' => 'Non autorisé'], 401);
            }

            // Trouver l'événement par son ID
            $event = Event::find($id);
            if (!$event) {
                return response()->json(['message' => 'Événement non trouvé'], 404);
            }

            // Vérifier si l'utilisateur connecté est l'organisateur
            if ($event->organizer_id != $user->id) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            // Soft-delete de l'événement
            $event->delete();

            return response()->json(['message' => 'Événement supprimé avec succès'], 200);
        } catch (Exception $e) {
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

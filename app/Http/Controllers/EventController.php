<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Event;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\CategoryEvent;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class EventController extends Controller
{
    // Lister tous les événements
    public function index(Request $request)
    {
        try {
            $categoryId = $request->query('category_id');

            $query = Event::with(['organizer.user', 'categories'])
                ->whereNull('deleted_at')
                ->where('event_status', '!=', 'supprimer');

            if ($categoryId) {
                $query->whereHas('categories', function ($q) use ($categoryId) {
                    $q->where('categories.id', $categoryId);
                });
            }

            $events = $query->get();

            // Ajouter le nombre de tickets disponibles pour chaque type de ticket
            $eventsWithTickets = $events->map(function ($event) {
                // Décoder `ticket_types` si c'est une chaîne JSON
                $ticketTypes = is_string($event->ticket_types) ? json_decode($event->ticket_types, true) : $event->ticket_types;

                // Vérifier que le décodage a réussi et que `ticket_types` est bien un tableau
                if (!is_array($ticketTypes)) {
                    $ticketTypes = [];
                }

                // Récupérer les types de tickets et leur quantité pour cet événement
                $ticketTypesWithAvailability = collect($ticketTypes)->map(function ($ticketType) use ($event) {
                    // Compter le nombre de tickets vendus pour ce type de ticket
                    $ticketsSold = DB::table('tickets')
                        ->where('event_id', $event->id)
                        ->where('ticket_type', $ticketType['type'])
                        ->count();

                    // Calculer le nombre de tickets disponibles pour ce type
                    $ticketsAvailable = max(0, $ticketType['quantity'] - $ticketsSold);

                    // Retourner les informations avec les tickets disponibles pour ce type
                    return [
                        'type' => $ticketType['type'],
                        'price' => $ticketType['price'],
                        'quantity' => $ticketType['quantity'],
                        'tickets_sold' => $ticketsSold,
                        'tickets_available' => $ticketsAvailable,
                    ];
                });

                // Retourner l'événement avec les informations de disponibilité des tickets par type
                return array_merge($event->toArray(), [
                    'ticket_types' => $ticketTypesWithAvailability,
                ]);
            });

            return response()->json($eventsWithTickets, 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Erreur lors de la récupération des événements', 'details' => $e->getMessage()], 500);
        }
    }





    // Créer un nouvel événement avec validation et messages d'erreur personnalisés    // Créer un nouvel événement avec validation et messages d'erreur personnalisés
    public function store(Request $request)
    {
        try {
            // Authentification de l'utilisateur
            $user = JWTAuth::user();
            if (!$user) {
                return response()->json(['message' => 'Non autorisé'], 401);
            }

            $data = json_decode($request->input('body'), true);

            // Validation des données de l'événement, des tickets et des relations
            $validator = Validator::make($data, [
                'name' => 'required|string|max:255',
                'date' => 'required|date|after:today',
                'time' => 'required',
                'location' => 'required|string|max:255',
                'description' => 'required|string',
                'ticket_types' => 'required|array|min:1',
                'ticket_types.*.type' => 'required|string|max:255',
                'ticket_types.*.price' => 'required|numeric|min:0',
                'ticket_types.*.quantity' => 'required|integer|min:1',
                'categories' => 'array|min:1', // Validation des catégories
                'categories.*' => 'exists:categories,id',
                'wallets' => 'required|array|min:1', // Validation des wallets
                'wallets.*' => 'exists:wallets,id'
            ], [
                'ticket_types.*.type.required' => 'Chaque type de ticket doit avoir un nom.',
                'ticket_types.*.price.required' => 'Chaque type de ticket doit avoir un prix.',
                'ticket_types.*.quantity.required' => 'Chaque type de ticket doit avoir une quantité.'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $validatedData = $validator->validated();

            // Gestion de l'upload de l'image bannière
            $banner = $request->file('banner');
            if (!$banner) {
                return response()->json(['error' => 'La bannière est obligatoire'], 400);
            }

            $bannerPath = $banner->store('public/events', 'public');
            $validatedData['banner'] = '/storage/' . $bannerPath;

            // Ajouter des identifiants entiers uniques à chaque type de ticket
            $ticketTypeId = 1;
            $ticketTypes = array_map(function ($ticketType) use (&$ticketTypeId) {
                $ticketType['id'] = $ticketTypeId++;
                return $ticketType;
            }, $validatedData['ticket_types']);

            // Création de l'événement
            $event = Event::create([
                'name' => $validatedData['name'],
                'description' => $validatedData['description'],
                'location' => $validatedData['location'],
                'date' => $validatedData['date'],
                'time' => $validatedData['time'],
                'banner' => $validatedData['banner'],
                'organizer_id' => $user->id,
                'ticket_types' => $ticketTypes, // Stockage des types de tickets avec leurs IDs en JSON
            ]);

            // Ajout des catégories à l'événement
            $event->categories()->sync($validatedData['categories']);

            // Ajout des méthodes de paiement à l'événement
            $event->wallets()->sync($validatedData['wallets']);

            return response()->json(['message' => 'Événement créé avec succès', 'event' => $event], 201);
        } catch (Exception $e) {
            Log::error('Erreur lors de la création de l\'événement: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la création de l\'événement',
                'details' => $e->getMessage()
            ], 500);
        }
    }



    // Voir les détails d'un événement spécifique
    public function show($id)
    {
        try {
            $event = Event::with(['organizer', 'organizer.user', 'categories', 'wallets'])->find($id);
            if (!$event) {
                return response()->json(['error' => 'Événement non trouvé'], 404);
            }

            $ticketTypes = collect($event->ticket_types)->map(function ($ticketType) use ($event) {
                $ticketsSold = DB::table('tickets')
                    ->where('event_id', $event->id)
                    ->where('ticket_type', $ticketType['type'])
                    ->count();

                $ticketsAvailable = max(0, $ticketType['quantity'] - $ticketsSold);
                return array_merge($ticketType, ['tickets_available' => $ticketsAvailable]);
            });

            return response()->json([
                'event' => $event,
                'ticket_types' => $ticketTypes,
                'tickets_remaining' => $ticketTypes->sum('tickets_available')
            ], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Erreur lors de la récupération de l\'événement', 'details' => $e->getMessage()], 500);
        }
    }



    // Voir les événements similaires
    public function similarEvents($id)
    {
        try {
            // Trouver l'événement d'origine
            $event = Event::with('categories')->find($id);

            if (!$event) {
                return response()->json(['erreur' => 'Événement non trouvé'], 404);
            }

            // Récupérer les catégories associées à cet événement
            $categoryIds = $event->categories->pluck('id')->toArray();

            // Trouver des événements similaires en fonction des catégories partagées
            $similarEvents = Event::with(['organizer.user', 'categories'])
                ->whereHas('categories', function ($query) use ($categoryIds) {
                    $query->whereIn('categories.id', $categoryIds);
                })
                ->where('id', '!=', $id) // Exclure l'événement actuel
                ->whereNull('deleted_at') // Exclure les événements supprimés
                ->where('event_status', '!=', 'supprimer')
                ->limit(5) // Limiter le nombre de résultats similaires
                ->get();

            return response()->json($similarEvents, 200);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Erreur lors de la récupération des événements similaires',
                'details' => $e->getMessage()
            ], 500);
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

    // Modifier un evenement
    public function update(Request $request, $id)
    {
        try {
            // Authentification de l'utilisateur
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json(['message' => 'Non autorisé'], 401);
            }

            // Récupération de l'événement à modifier
            $event = Event::find($id);
            if (!$event) {
                return response()->json(['message' => 'Événement non trouvé'], 404);
            }

            // Vérifier si l'utilisateur connecté est l'organisateur de l'événement
            if ($event->organizer_id != $user->id) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            $data = json_decode($request->input('body'), true);

            // Validation des données d'entrée
            $validator = Validator::make($data, [
                'name' => 'sometimes|string|max:255',
                'date' => 'sometimes|date|after:today',
                'time' => 'sometimes',
                'location' => 'sometimes|string|max:255',
                'description' => 'sometimes|string',
                'ticket_types' => 'sometimes|array|min:1',
                'ticket_types.*.type' => 'required|string|max:255',
                'ticket_types.*.price' => 'required|numeric|min:0',
                'ticket_types.*.quantity' => 'required|integer|min:1',
                'categories' => 'sometimes|array|min:1',
                'categories.*' => 'exists:categories,id',
                'wallets' => 'sometimes|array|min:1',
                'wallets.*' => 'exists:wallets,id',
                'banner' => 'sometimes|file|mimes:jpeg,png,jpg,gif|max:2048'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $validatedData = $validator->validated();

            // Gestion de l'image bannière si présente dans la requête
            if ($request->hasFile('banner')) {
                if ($event->banner) {
                    Storage::disk('public')->delete($event->banner);
                }
                $bannerPath = $request->file('banner')->store('events', 'public');
                $validatedData['banner'] = '/storage/' . $bannerPath;
            }

            // Mise à jour des informations principales de l'événement
            $event->update([
                'name' => $validatedData['name'] ?? $event->name,
                'date' => $validatedData['date'] ?? $event->date,
                'time' => $validatedData['time'] ?? $event->time,
                'location' => $validatedData['location'] ?? $event->location,
                'description' => $validatedData['description'] ?? $event->description,
                'banner' => $validatedData['banner'] ?? $event->banner,
                'ticket_types' => $validatedData['ticket_types'] ?? $event->ticket_types // Mise à jour des types de tickets en JSON
            ]);

            // Mise à jour des catégories et des méthodes de paiement
            if (isset($validatedData['categories'])) {
                $event->categories()->sync($validatedData['categories']);
            }
            if (isset($validatedData['wallets'])) {
                $event->wallets()->sync($validatedData['wallets']);
            }

            return response()->json(['message' => 'Événement mis à jour avec succès', 'event' => $event], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Erreur lors de la mise à jour de l\'événement', 'details' => $e->getMessage()], 500);
        }
    }



    public function dashboard($id)
    {
        try {
            $event = Event::with(['categories', 'wallets'])->findOrFail($id);

            // Calcul de la durée depuis la publication
            $publishedAt = $event->created_at;

            if ($publishedAt) {
                $now = now();
                $diffInDays = floor($now->diffInDays($publishedAt));
                $diffInHours = floor($now->diffInHours($publishedAt) % 24); // heures restantes après les jours
                $diffInMinutes = floor($now->diffInMinutes($publishedAt) % 60); // minutes restantes après les heures

                // Construire une chaîne de durée arrondie
                $durationSincePublication = "{$diffInDays} jours, {$diffInHours}:{$diffInMinutes}";
            } else {
                $durationSincePublication = null;
            }

            // Format de la date et heure de publication
            $formattedDate = $publishedAt ? $publishedAt->format('d-m-Y') : null; // Format jour-mois-année
            $formattedTime = $publishedAt ? $publishedAt->format('H:i') : null; // Heure:min arrondie


            // Calcul des statistiques
            $ticketsSold = DB::table('tickets')->where('event_id', $event->id)->count();
            $revenue = $ticketsSold * $event->ticket_price;
            $ticketsRemaining = $event->ticket_quantity - $ticketsSold;

            $ticketHolders = DB::table('tickets')
                ->join('users', 'tickets.user_id', '=', 'users.id')
                ->where('tickets.event_id', $event->id)
                ->select('users.id', 'users.name', 'users.email', 'tickets.created_at as purchase_date')
                ->get();

            return response()->json([
                'event' => $event,
                'published_date' => $formattedDate,
                'published_time' => $formattedTime,
                'duration_since_publication' => $durationSincePublication,
                'statistics' => [
                    'tickets_sold' => $ticketsSold,
                    'tickets_remaining' => $ticketsRemaining,
                    'revenue' => $revenue,
                ],
                'ticket_holders' => $ticketHolders
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Erreur lors de la récupération du tableau de bord', 'details' => $e->getMessage()], 500);
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

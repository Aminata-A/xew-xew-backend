<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\Wallet;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use App\Models\AnonymousUser;
use App\Models\RegisteredUser;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\StoreTicketRequest;
use GuzzleHttp\Exception\RequestException;

class TicketController extends Controller
{
    public function index()
    {
        // Vérifier si l'utilisateur est connecté
        if (!Auth::check()) {
            return response()->json(['message' => 'Veuillez vous connecter pour voir vos billets.'], 401);
        }

        dd(Auth::user());

        // Récupérer l'utilisateur connecté
        $user = Auth::user();

        // Récupérer les billets achetés par l'utilisateur connecté avec les détails de l'événement
        $tickets = Ticket::where('user_id', $user->id)
            ->with('event') // Inclure les détails de l'événement associé
            ->get();

        // Vérifier si l'utilisateur a des billets
        if ($tickets->isEmpty()) {
            return response()->json(['message' => 'Aucun billet trouvé pour cet utilisateur.'], 404);
        }

        // Formater les billets pour une meilleure lisibilité
        $ticketData = $tickets->map(function ($ticket) {
            return [
                'ticket_id' => $ticket->id,
                'event_name' => $ticket->event->name ?? 'Nom de l\'événement indisponible',
                'event_date' => $ticket->event->date ?? 'Date de l\'événement indisponible',
                'purchase_date' => $ticket->created_at->format('Y-m-d H:i:s'),
            ];
        });

        // Retourner les billets sous forme de réponse JSON formatée
        return response()->json([
            'message' => 'Billets récupérés avec succès.',
            'tickets' => $ticketData
        ], 200);
    }


    public function store(StoreTicketRequest $request)
    {
        try {
            // Récupérer les données JSON de la requête
            $data = $request->json()->all();
            $user = $request->user();

            // Vérifier ou créer l'utilisateur
            if (!$user) {
                $user = User::where('email', $data['email'])->first();
                if ($user && $user->userable instanceof RegisteredUser) {
                    return response()->json(['message' => 'Merci de vous authentifier !'], 401);
                }

                if (!$user) {
                    $anonymousUser = AnonymousUser::create();
                    $user = new User([
                        'name' => $data['name'],
                        'email' => $data['email'],
                    ]);
                    $user->userable()->associate($anonymousUser);
                    $user->save();
                }
            }

            // Vérifier si l'événement existe
            $event = Event::find($data['event_id']);
            if (!$event) {
                return response()->json(['message' => 'Événement non trouvé.'], 404);
            }

            // Si le prix du ticket est 0, créer directement le ticket sans transaction
            if ($event->ticket_price == 0) {
                // Si le prix du ticket est 0, on crée directement les tickets
                $tickets = []; // Initialiser un tableau pour stocker les tickets créés
                for ($i = 0; $i < $data['quantity']; $i++) {
                    $ticket = Ticket::create([
                        'event_id' => $event->id,
                        'user_id' => $user->id,
                    ]);
                    // Ajouter une description du ticket dans le tableau $tickets
                    $tickets[] = $event->name . " - Ticket #" . ($i + 1);
                }

                return response()->json([
                    'message' => 'Tickets créés avec succès pour un événement gratuit.',
                    'tickets' => $tickets // Retourner la liste des tickets créés
                ], 201);
            }
            // Si le prix n'est pas 0, passer par Naboopay pour la transaction
            $unitPrice = $event->ticket_price;

            // Préparer la transaction Naboopay
            $paymentMethods = array_map('strtoupper', $event->wallets->pluck('name')->toArray());
            $transactionData = [
                'method_of_payment' => $paymentMethods,
                'products' => [
                    [
                        'name' => "Ticket pour l'événement " . $event->name,
                        'category' => 'Event Ticket',
                        'amount' => $unitPrice,
                        'quantity' => $data['quantity'],
                        'description' => 'Billet pour assister à l\'événement: ' . $event->name,
                        ]
                    ],
                    'success_url' => 'https://smartdevafrica.com',
                    'error_url' => 'https://smartdevafrica.com',
                    'is_escrow' => false,
                    'is_merchant' => false,
                ];

                // Gérer la transaction Naboopay
                $response = $this->createNaboopayTransaction($transactionData);

                if (!$response || $response->getStatusCode() !== 200) {
                    return response()->json(['message' => 'La transaction a échoué.'], 500);
                }

                // Récupérer les détails de la transaction
                $transactionDetails = json_decode($response->getBody(), true);
                $payment_url = $transactionDetails['checkout_url'];

                // Créer les tickets et les associer à l'événement et à l'utilisateur
                for ($i = 0; $i < $data['quantity']; $i++) {
                    $ticket = Ticket::create([
                        'event_id' => $event->id,
                        'naboo_order_id' => $transactionDetails['order_id'],
                        'url_payment' => $payment_url,
                        'user_id' => $user->id
                    ]);
                }

                return response()->json(['payment_url' => $payment_url], 201);
            } catch (\Exception $e) {
                // Gestion des erreurs générales
                return response()->json(['message' => 'Erreur lors de la création du billet : ' . $e->getMessage()], 500);
            }
        }


        /**
        * Fonction utilitaire pour créer une transaction sur Naboopay
        */
        private function createNaboopayTransaction(array $transactionData)
        {
            $client = new Client(['verify' => false]); // Créer une instance de Guzzle

            try {
                $response = $client->request('PUT', 'https://api.naboopay.com/api/v1/transaction/create-transaction', [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'Authorization' => 'Bearer ' . env('NABOOPAY_API_KEY'),
                    ],
                    'json' => $transactionData,
                ]);

                return $response;
            } catch (RequestException $e) {
                dd($e);
                // Gérer les erreurs (timeout, connexion, etc.)
                return null;
            }
        }
    }

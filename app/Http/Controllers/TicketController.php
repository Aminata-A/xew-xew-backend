<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\Wallet;
use GuzzleHttp\Client;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\AnonymousUser;
use App\Models\RegisteredUser;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use App\Http\Requests\StoreTicketRequest;
use GuzzleHttp\Exception\RequestException;

class TicketController extends Controller
{
    // Récupérer les billets de l'utilisateur authentifié
    public function index()
    {
        try {
            // Authentifier l'utilisateur via JWT
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['message' => 'Utilisateur non authentifié'], 401);
            }

            // Récupérer les tickets de l'utilisateur avec les événements et les organisateurs associés
            $tickets = Ticket::where('user_id', $user->id)
                ->with(['event', 'event.organizer'])
                ->get();

            // Vérifier si aucun ticket n'est trouvé
            if ($tickets->isEmpty()) {
                return response()->json([
                    'message' => 'Aucun billet trouvé pour cet utilisateur.',
                    'code' => 404
                ], 404);
            }

            // Formater les tickets pour inclure les détails de chaque événement, organisateur, et tous les types de tickets
            $ticketData = $tickets->map(function ($ticket) {
                // Récupérer tous les types de tickets disponibles pour l'événement
                $allTicketTypes = $ticket->event->ticket_types;

                return [
                    'ticket_id' => $ticket->id,
                    'event_name' => $ticket->event->name ?? 'Nom de l\'événement indisponible',
                    'event_date' => $ticket->event->date ?? 'Date de l\'événement indisponible',
                    'event_location' => $ticket->event->location ?? 'Lieu de l\'événement indisponible',
                    'organizer_name' => $ticket->event->organizer->name ?? 'Nom de l\'organisateur indisponible',
                    'purchase_date' => $ticket->created_at->format('Y-m-d H:i:s'),
                    'ticket_types' => $allTicketTypes ?? [], // Inclure tous les types de tickets
                ];
            });

            // Retourner les tickets sous forme de réponse JSON
            return response()->json([
                'message' => 'Billets récupérés avec succès.',
                'tickets' => $ticketData,
                'code' => 200
            ], 200);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json(['message' => 'Le token a expiré. Veuillez vous reconnecter.'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(['message' => 'Le token est invalide.'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['message' => 'Token absent.'], 401);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des billets:', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la récupération des billets.', 'error' => $e->getMessage()], 500);
        }
    }


    // Créer un nouveau billet avec validation
    public function store(StoreTicketRequest $request)
    {
        try {
            $data = $request->json()->all();

            // Authentifier l'utilisateur via JWT
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['message' => 'Utilisateur non authentifié.'], 401);
            }

            // Vérifier si l'événement existe
            $event = Event::find($data['event_id']);
            if (!$event) {
                return response()->json(['message' => 'Événement non trouvé.'], 404);
            }

            // Vérifier que le type de ticket existe dans l'événement
            $ticketType = collect($event->ticket_types)->firstWhere('type', $data['ticket_type']);
            if (!$ticketType) {
                return response()->json(['message' => 'Type de ticket non trouvé pour cet événement.'], 404);
            }

            // Limiter le nombre de billets à 5 par utilisateur pour ce type de ticket
            $maxTicketsPerUser = 5;
            $userTicketsForEvent = Ticket::where('event_id', $event->id)
                ->where('user_id', $user->id)
                ->where('ticket_type', $data['ticket_type'])
                ->count();

            if ($userTicketsForEvent + $data['quantity'] > $maxTicketsPerUser) {
                return response()->json([
                    'message' => 'Vous avez dépassé le nombre limite d\'achat de billets par type (max ' . $maxTicketsPerUser . ').'
                ], 400);
            }

            // Compter le nombre total de tickets déjà achetés pour ce type
            $ticketsBought = Ticket::where('event_id', $event->id)
                ->where('ticket_type', $data['ticket_type'])
                ->count();

            if ($ticketsBought + $data['quantity'] > $ticketType['quantity']) {
                $availableTickets = $ticketType['quantity'] - $ticketsBought;
                return response()->json([
                    'message' => 'Il ne reste que ' . $availableTickets . ' tickets pour ce type.'
                ], 400);
            }

            // Gestion des tickets gratuits
            if ($ticketType['price'] == 0) {
                $tickets = [];
                for ($i = 0; $i < $data['quantity']; $i++) {
                    $ticket = Ticket::create([
                        'event_id' => $event->id,
                        'user_id' => $user->id,
                        'ticket_type' => $data['ticket_type'],
                        'is_paid' => true
                    ]);
                    $tickets[] = $ticket;
                }

                return response()->json([
                    'message' => 'Tickets créés avec succès pour un type de ticket gratuit.',
                    'tickets' => $tickets
                ], 201);
            }

            // Si l'événement est payant, gérer la transaction via Naboopay
            $unitPrice = $ticketType['price'];
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
                'success_url' => env('APP_URL_DEV') . '/tickets/webhook',
                'error_url' => env('APP_URL_DEV') . '/tickets/error',
                'is_escrow' => false,
                'is_merchant' => false,
            ];

            // Gérer la transaction via Naboopay
            $response = $this->createNaboopayTransaction($transactionData);

            if (!$response || $response->getStatusCode() !== 200) {
                return response()->json(['message' => 'La transaction a échoué.'], 500);
            }

            $transactionDetails = json_decode($response->getBody(), true);

            // Vérifiez que la clé ticket_type est bien définie dans les données de la requête
            if (empty($data['ticket_type'])) {
                return response()->json(['message' => 'Le type de ticket est requis.'], 400);
            }

            // Créer des tickets avec statut "not paid"
            $tickets = [];
            for ($i = 0; $i < $data['quantity']; $i++) {
                $ticket = Ticket::create([
                    'event_id' => $event->id,
                    'user_id' => $user->id,
                    'ticket_type' => $data['ticket_type'], // Vérifiez que cette valeur est bien définie
                    'naboo_order_id' => $transactionDetails['order_id'],
                    'is_paid' => false,
                ]);
                $tickets[] = $ticket;
            }



            return response()->json(['payment_url' => $transactionDetails['checkout_url']], 201);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la création du billet:', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la création du billet.', 'error' => $e->getMessage()], 500);
        }
    }



    // Utilitaire pour créer une transaction via Naboopay
    private function createNaboopayTransaction(array $transactionData)
    {
        $client = new Client(['verify' => false]);
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
            Log::error('Erreur lors de la création de la transaction:', ['error' => $e->getMessage()]);
            return null;
        }
    }

    // Gérer le webhook pour valider un paiement
    public function webhook(Request $request)
    {
        $status = $request->input('transaction_status');

        if ($status != 'success') {
            return response()->json(['message' => 'Le paiement a échoué.'], 400);
        }

        $orderId = $request->input('order_id');
        $amount = $request->input('amount');

        $tickets = Ticket::where('naboo_order_id', $orderId)->get();

        if ($tickets->isEmpty()) {
            return response()->json(['message' => 'Aucun ticket trouvé pour cette commande.'], 404);
        }

        $event = $tickets[0]->event;

        if ($event->ticket_quantity < count($tickets)) {
            Ticket::where('naboo_order_id', $orderId)->delete();
            return response()->json(['message' => 'Nombre de tickets insuffisant pour cet événement.'], 400);
        }

        $transaction = Transaction::create([
            'order_id' => $orderId,
            'amount' => $amount,
            'status' => $status,
            'user_id' => $tickets[0]->user_id,
            'transactionable_id' => $event->id,
            'transactionable_type' => Event::class,
        ]);

        foreach ($tickets as $ticket) {
            $ticket->update(['is_paid' => true]);
            $event->decrement('ticket_quantity');
        }

        return response()->json(['message' => 'Paiement validé et tickets créés.'], 200);
    }

    // Récupérer un billet avec les détails de l'événement
    public function show($id)
    {
        try {
            // Authentifier l'utilisateur via JWT
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['message' => 'Utilisateur non authentifié'], 401);
            }

            // Trouver le billet avec les détails de l'événement
            $ticket = Ticket::with(['event'])->find($id);

            // Vérifier si le billet existe
            if (!$ticket) {
                return response()->json(['message' => 'Billet non trouvé'], 404);
            }

            // Récupérer les informations du type de ticket (prix, etc.)
            $ticketType = collect($ticket->event->ticket_types)->firstWhere('type', $ticket->ticket_type);

            if (!$ticketType) {
                return response()->json(['message' => 'Type de ticket non trouvé'], 404);
            }

            // Calculer les tickets restants pour l'événement
            $totalTickets = $ticket->event->ticket_quantity;
            $ticketsSold = Ticket::where('event_id', $ticket->event->id)->count();
            $ticketsRemaining = $totalTickets - $ticketsSold;

            // Vérifier s'il reste des tickets
            // if ($ticketsRemaining <= 0) {
            //     return response()->json(['message' => 'Il ne reste plus de tickets disponibles pour cet événement.'], 403);
            // }

            // Construction des données du billet
            $ticketData = [
                'qr_code' => Crypt::encrypt($ticket->id),
                'ticket_id' => $ticket->id,
                'purchase_date' => $ticket->created_at->format('Y-m-d H:i:s'),
                'is_paid' => $ticket->is_paid,
                'is_scanned' => $ticket->is_scanned,
                'tickets_remaining' => $ticketsRemaining,
                'event' => [
                    'event_name' => $ticket->event->name ?? 'Nom de l\'événement indisponible',
                    'event_date' => $ticket->event->date ?? 'Date de l\'événement indisponible',
                    'event_location' => $ticket->event->location ?? 'Lieu de l\'événement indisponible',
                    'event_price' => $ticketType['price'] ?? 'Prix de l\'événement indisponible',
                    'event_description' => $ticket->event->description ?? 'Description de l\'événement indisponible',
                    'event_banner' => $ticket->event->banner ?? 'Image de l\'événement indisponible',
                    'ticket_type' => [
                        'type' => $ticketType['type'] ?? 'Type de ticket indisponible',
                        'price' => $ticketType['price'] ?? 'Prix indisponible',
                        'quantity' => $ticketType['quantity'] ?? 'Quantité indisponible',
                    ]
                ],
                'buyer' => [
                    'name' => $user->user->name ?? 'Nom de l\'acheteur indisponible',
                    'email' => $user->user->email ?? 'Email de l\'acheteur indisponible',
                ],
            ];

            // Retourner les données du billet au format JSON
            return response()->json($ticketData, 200);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json(['message' => 'Le token a expiré. Veuillez vous reconnecter.'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(['message' => 'Le token est invalide.'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['message' => 'Token absent.'], 401);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération du billet:', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la récupération du billet.', 'error' => $e->getMessage()], 500);
        }
    }
}

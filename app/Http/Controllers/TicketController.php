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
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\StoreTicketRequest;
use GuzzleHttp\Exception\RequestException;

class TicketController extends Controller
{
    public function index()
    {
        try {
            // Authentifier l'utilisateur via JWT
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['message' => 'Utilisateur non authentifié'], 401);
            }

            // Récupérer les tickets de l'utilisateur authentifié avec les événements et les organisateurs associés
            $tickets = Ticket::where('user_id', $user->id)
            ->with(['event', 'event.organizer'])  // Inclure les informations de l'événement et de l'organisateur
            ->get();

            // Si aucun ticket n'est trouvé
            if ($tickets->isEmpty()) {
                return response()->json([
                    'message' => 'Aucun billet trouvé pour cet utilisateur.',
                    'code' => 404
                ], 404);
            }

            // Formater les tickets pour inclure les détails de chaque événement et de l'organisateur
            $ticketData = $tickets->map(function ($ticket) {
                return [
                    'ticket_id' => $ticket->id,
                    'event_name' => $ticket->event->name ?? 'Nom de l\'événement indisponible',
                    'event_date' => $ticket->event->date ?? 'Date de l\'événement indisponible',
                    'event_location' => $ticket->event->location ?? 'Lieu de l\'événement indisponible',
                    'event_price' => $ticket->event->ticket_price ?? 'Prix de l\'événement indisponible',
                    'organizer_name' => $ticket->event->organizer->name ?? 'Nom de l\'organisateur indisponible',
                    'purchase_date' => $ticket->created_at->format('Y-m-d H:i:s'),
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





    public function store(StoreTicketRequest $request)
    {
        try {
            // Récupérer les données JSON de la requête
            $data = $request->json()->all();

            // Tenter d'authentifier l'utilisateur via JWT, mais permettre l'accès si non authentifié
            $user = null;
            try {
                $user = JWTAuth::parseToken()->authenticate(); // Utilisateur connecté via JWT
            } catch (\Exception $e) {
                // Si l'utilisateur n'est pas authentifié, vérifier si des informations d'utilisateur sont fournies
                if (empty($data['name']) || empty($data['email'])) {
                    return response()->json(['message' => 'Veuillez fournir un nom et un email pour acheter un billet.'], 400);
                }
            }

            // Si l'utilisateur n'est pas connecté, créer un utilisateur temporaire
            if (!$user) {
                $user = AnonymousUser::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                ]);
            }

            // Vérifier si l'événement existe
            $event = Event::find($data['event_id']);
            if (!$event) {
                return response()->json(['message' => 'Événement non trouvé.'], 404);
            }

            // Si l'événement est gratuit (prix du ticket est 0), créer directement les tickets
            if ($event->ticket_price == 0) {
                $tickets = [];
                for ($i = 0; $i < $data['quantity']; $i++) {
                    $ticket = Ticket::create([
                        'event_id' => $event->id,
                        'user_id' => $user->id, // Utiliser l'utilisateur créé ou authentifié
                    ]);
                    $tickets[] = $event->name . " - Ticket #" . ($i + 1);
                }

                return response()->json([
                    'message' => 'Tickets créés avec succès pour un événement gratuit.',
                    'tickets' => $tickets
                ], 201);
            }

            // Si l'événement est payant, gérer la transaction via Naboopay
            $unitPrice = $event->ticket_price;
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
                'success_url' => env('APP_URL') . '/tickets/webhook',
                'error_url' => env('APP_URL') . '/tickets/error',
                'is_escrow' => false,
                'is_merchant' => false,
            ];

            // Gérer la transaction via Naboopay
            $response = $this->createNaboopayTransaction($transactionData);

            // Vérifier si l'appel à Naboopay a réussi
            if (!$response || $response->getStatusCode() !== 200) {
                return response()->json(['message' => 'La transaction a échoué.'], 500);
            }

            // Assigner les détails de la transaction uniquement après avoir validé que l'appel a réussi
            $transactionDetails = json_decode($response->getBody(), true);

            // Boucler sur la quantité de tickets pour créer les tickets avec un statut "not paid"
            $tickets = [];
            for ($i = 0; $i < $data['quantity']; $i++) {
                $ticket = Ticket::create([
                    'event_id' => $event->id,
                    'user_id' => $user->id,  // Utiliser l'utilisateur créé ou authentifié
                    'naboo_order_id' => $transactionDetails['order_id'],
                    'is_paid' => false,  // Statut de paiement initial
                ]);
                $tickets[] = $ticket;
            }

            $payment_url = $transactionDetails['checkout_url'];

            return response()->json(['payment_url' => $payment_url], 201);

        } catch (\Exception $e) {
            // Gestion des erreurs
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

        // Ajout d'une méthode webhook pour gérer la validation du paiement
        public function webhook(Request $request)
        {
            $status = $request->input('transaction_status');

            if ($status != 'success') {
                return response()->json(['message' => 'Le paiement a échoué.'], 400);
            }

            $orderId = $request->input('order_id');
            $amount = $request->input('amount');

            // Récupérer les tickets de la commande
            $tickets = Ticket::where('naboo_order_id', $orderId)->get();

            if ($tickets->isEmpty()) {
                return response()->json(['message' => 'Aucun ticket trouvé pour cette commande.'], 404);
            }

            // Récupérer l'événement pour vérifier le nombre de tickets restants
            $event = $tickets[0]->event;

            // Vérifier si le nombre de tickets restants est suffisant
            if ($event->ticket_quantity < count($tickets)) {
                // Si pas assez de tickets disponibles, annuler le paiement et supprimer les tickets
                // TODO: Implémenter l'annulation du paiement via Naboopay
                Ticket::where('naboo_order_id', $orderId)->delete();
                return response()->json(['message' => 'Nombre de tickets insuffisant pour cet événement.'], 400);
            }

            // Créer la transaction
            $transaction = Transaction::create([
                'order_id' => $orderId,
                'amount' => $amount,
                'status' => $status,
                'user_id' => $tickets[0]->user_id,
                'transactionable_id' => $event->id,  // Associer à l'événement
                'transactionable_type' => Event::class, // Type d'entité transactionnée
            ]);

            // Mettre à jour les tickets pour indiquer qu'ils sont payés et décrémenter le nombre de tickets disponibles
            foreach ($tickets as $ticket) {
                $ticket->update(['is_paid' => true]);
                $event->decrement('ticket_quantity');
            }

            return response()->json(['message' => 'Paiement validé et tickets créés.'], 200);
        }

    }

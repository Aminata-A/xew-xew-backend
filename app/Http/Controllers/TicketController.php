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

            // Si l'événement est gratuit, créer directement les tickets
            if ($event->ticket_price == 0) {
                $tickets = [];
                for ($i = 0; $i < $data['quantity']; $i++) {
                    $ticket = Ticket::create([
                        'event_id' => $event->id,
                        'user_id' => $user->id,
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

            if (!$response || $response->getStatusCode() !== 200) {
                return response()->json(['message' => 'La transaction a échoué.'], 500);
            }

            $transactionDetails = json_decode($response->getBody(), true);

            // Créer des tickets avec statut "not paid"
            $tickets = [];
            for ($i = 0; $i < $data['quantity']; $i++) {
                $ticket = Ticket::create([
                    'event_id' => $event->id,
                    'user_id' => $user->id,
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
                dd($transactionData),
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

    // Voir les détails d'un billet spécifique
    public function show($id)
    {
        try {
            // Authentifier l'utilisateur via JWT
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['message' => 'Utilisateur non authentifié'], 401);
            }

            // Trouver le billet avec les détails de l'événement et de l'acheteur
            $ticket = Ticket::with(['event', 'participant'])->find($id);

            // Vérifier si le billet existe
            if (!$ticket) {
                return response()->json(['message' => 'Billet non trouvé'], 404);
            }

            $ticketData = [
                'qr_code' => Crypt::encrypt($ticket->id),
                'ticket_id' => $ticket->id,
                'purchase_date' => $ticket->created_at->format('Y-m-d H:i:s'),
                'is_paid' => $ticket->is_paid,
                'event' => [
                    'event_name' => $ticket->event->name ?? 'Nom de l\'événement indisponible',
                    'event_date' => $ticket->event->date ?? 'Date de l\'événement indisponible',
                    'event_location' => $ticket->event->location ?? 'Lieu de l\'événement indisponible',
                    'event_price' => $ticket->event->ticket_price ?? 'Prix de l\'événement indisponible',
                ],
                'buyer' => [
                    'name' => $user->user->name ?? 'Nom de l\'acheteur indisponible',
                    'email' => $user->user->email ?? 'Email de l\'acheteur indisponible',
                ],
            ];

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

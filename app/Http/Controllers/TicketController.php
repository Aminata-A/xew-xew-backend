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
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\StoreTicketRequest;
use GuzzleHttp\Exception\RequestException;

class TicketController extends Controller
{
    public function index()
    {
        try {
            // Authentifier l'utilisateur via JWT
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json(['message' => 'Utilisateur non authentifié'], 401);
            }

            // Récupérer les tickets de l'utilisateur authentifié
            $tickets = Ticket::where('user_id', $user->id)
                ->with('event')  // Inclure les informations de l'événement associé
                ->get();

            // Si aucun ticket n'est trouvé
            if ($tickets->isEmpty()) {
                return response()->json([
                    'message' => 'Aucun billet trouvé pour cet utilisateur.',
                    'code' => 404
                ], 404);
            }

            // Formater les tickets pour une meilleure présentation
            $ticketData = $tickets->map(function ($ticket) {
                return [
                    'ticket_id' => $ticket->id,
                    'event_name' => $ticket->event->name ?? 'Nom de l\'événement indisponible',
                    'event_date' => $ticket->event->date ?? 'Date de l\'événement indisponible',
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
            return response()->json(['message' => 'Erreur lors de la récupération des billets.', 'error' => $e->getMessage()], 500);
        }
    }

    public function store(StoreTicketRequest $request)
    {
        try {
            // Récupérer les données JSON de la requête
            $data = $request->json()->all();

            // Authentifier l'utilisateur via JWT, ou le définir comme null s'il n'est pas connecté
            $user = null;
            $name = null;
            $email = null;

            // Vérifier si l'utilisateur est connecté via JWT
            try {
                $user = JWTAuth::parseToken()->authenticate();
            } catch (\Exception $e) {
                // Pas d'utilisateur connecté, on continue pour l'utilisateur non connecté
            }

            if ($user) {
                // Si l'utilisateur est connecté, récupérer son nom et son email via la relation userable
                $name = $user->user->name ?? $user->name;
                $email = $user->user->email;

            } else {
                // Si l'utilisateur n'est pas connecté, utiliser les données fournies dans la requête
                $name = $data['name'] ?? null;
                $email = $data['email'] ?? null;

                // Si l'email ou le nom ne sont pas fournis, retourner une erreur
                if (!$name || !$email) {
                    return response()->json(['message' => 'Nom et Email sont requis pour les utilisateurs non authentifiés.'], 400);
                }

                // Vérifier si un utilisateur avec cet email existe déjà
                $user = User::where('email', $email)->first();

                if ($user && $user->userable instanceof RegisteredUser) {
                    // Si l'utilisateur existe et est un RegisteredUser, il doit se connecter
                    return response()->json(['message' => 'Merci de vous authentifier pour continuer.'], 401);
                }

                // Si l'utilisateur n'existe pas, créer un utilisateur anonyme
                if (!$user) {
                    $anonymousUser = AnonymousUser::create();
                    $user = new User([
                        'name' => $name,
                        'email' => $email,
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

            // Si l'événement est gratuit (prix du ticket est 0), créer directement les tickets
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
                'success_url' => env('APP_URL') . '/tickets/webhook', // Assurez-vous que APP_URL commence par https://
                'error_url' => env('APP_URL') . '/tickets/error',     // Assurez-vous que APP_URL commence par https://
                'is_escrow' => false,
                'is_merchant' => false,
            ];


                // Gérer la transaction via Naboopay
                $response = $this->createNaboopayTransaction($transactionData);

                if (!$response || $response->getStatusCode() !== 200) {
                    return response()->json(['message' => 'La transaction a échoué.'], 500);
                }

                // Récupérer les détails de la transaction
                $transactionDetails = json_decode($response->getBody(), true);
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
        $orderId = $request->input('order_id');
        $status = $request->input('status');

        // Récupérer la transaction via l'order_id
        $transaction = Transaction::where('order_id', $orderId)->first();

        if (!$transaction) {
            return response()->json(['message' => 'Transaction non trouvée.'], 404);
        }

        if ($status === 'success') {
            // Créer les tickets après le succès du paiement
            for ($i = 0; $i < $request->input('quantity'); $i++) {
                Ticket::create([
                    'event_id' => $transaction->transactionable_id,
                    'user_id' => $transaction->user_id,
                    'is_paid' => true,
                ]);
            }

            return response()->json(['message' => 'Paiement validé et tickets créés.'], 200);
        } else {
            return response()->json(['message' => 'Le paiement a échoué.'], 400);
        }
    }
}

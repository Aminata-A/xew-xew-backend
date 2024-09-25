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
use GuzzleHttp\Exception\RequestException;

class TicketController extends Controller
{
    public function store(Request $request)
    {
        // Vérification si l'utilisateur est connecté

        // Récupérer les données JSON de la requête
        $data = $request->json()->all();
        $user = $request->user();
        // TODO: Ajouter une validation a faire ici AVANT DE continuer
        if (!$user) {
            $user = User::where('email', $data['email'])->first();
            if ($user && $user->userable instanceof RegisteredUser) {
                return response()->json(['message' => 'Merci de vous authentifier !'], 401);
            }

            if (!$user) {
                $anonymousUser = AnonymousUser::create();
                // Associer l'utilisateur de base au anonymousUser
                $user = new User([
                    'name' => $data['name'],
                    'email' => $data['email'],
                ]);
                $user->userable()->associate($anonymousUser);
                $user->save();
            }
        }

        // Récupération des détails de l'événement
        $event = Event::find($data['event_id']);
        if (!$event) {
            return response()->json(['message' => 'Événement non trouvé'], 404);
        }

        // Calcul du nombre de tickets disponibles et vérification
        // $availableTickets = $event->ticket_quantity;
        // $soldTickets = Ticket::where('event_id', $event->id)->sum('quantity');

        // if ($soldTickets >= $availableTickets) {
        //     return response()->json(['message' => 'Aucun ticket disponible pour cet événement.'], 400);
        // }

        // Calcul du prix total
        $unitPrice = $event->ticket_price;

        // Récupération des méthodes de paiement associées à l'événement
        $paymentMethods = array_map('strtoupper',$event->wallets->pluck('name')->toArray());

        // Préparation des données pour la transaction Naboopay
        $transactionData = [
            'method_of_payment' => $paymentMethods,
            'products' => [
                [
                    'name' => "Ticket pour l'événement " . $event->name,
                    'category' => 'Event Ticket',
                    'amount' => $unitPrice,
                    'quantity' => $data['quantity'],
                    'description' => 'Billet pour assister à l\'evénement: ' . $event->name,
                ]
            ],
            'success_url' => 'https://smartdevafrica.com',
            'error_url' => 'https://smartdevafrica.com',
            'is_escrow' => false,
            'is_merchant' => false
        ];

        // Création de la transaction via Naboopay
        $response = $this->createNaboopayTransaction($transactionData);

        if (!$response || $response->getStatusCode() !== 200) {
            return response()->json(['message' => 'La transaction a échoué.'], 500);
        }

        // Décodage des détails de la transaction
        $transactionDetails = json_decode($response->getBody(), true);

        $payment_url = $transactionDetails['checkout_url'];
        // Création du billet (ticket)
        for ($i = 0; $i < $data['quantity']; $i++) {
            $ticket = Ticket::create([
                'event_id' => $event->id,
                'naboo_order_id' => $transactionDetails['order_id'],
                'url_payment' => $payment_url,
                'user_id' => $user->id
            ]);
        }
        return response()->json(['payment_url' => $payment_url], 201);
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

<?php

use App\Models\Ticket;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EventController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\Auth\VerifyCodeController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Auth\AuthentificationController;

// Routes publiques pour les authentifications
Route::post('auth/register', [AuthentificationController::class, 'register'])->name('register');
Route::post('auth/login', [AuthentificationController::class, 'login'])->name('login');

// Routes pour la vérification de l'email et du code
Route::post('auth/verify-email', [VerifyEmailController::class, 'verify']);
Route::post('auth/verify-code', [VerifyCodeController::class, 'verifyCode']);

Route::get('auth/user-profile', [AuthentificationController::class, 'getUserProfile']);

Route::post('auth/logout', [AuthentificationController::class, 'logout']);



// Routes protégées par le token (auth:api)
Route::middleware('auth:api')->group(function () {
    // Routes pour les événements (Création, Modification, Suppression)
    Route::apiResource('events', EventController::class)->only(['store', 'destroy']);

    // Route pour mettre à jour un événement
    Route::post('events/{event}/update', [EventController::class, 'update'])->name('events.update');

    // Route pour restaurer un événement supprimé
    Route::post('events/{event}/restore', [EventController::class, 'restore']);

    // Route pour supprimer définitivement un événement
    Route::delete('events/{event}/force-destroy', [EventController::class, 'forceDestroy']);

    // Route pour afficher les événements archivés
    Route::get('events/trash', [EventController::class, 'trash']);

    // Routes pour les catégories
    Route::apiResource('categories', CategoryController::class)->only(['store', 'update', 'destroy']);



    // Routes pour afficher les billets pour un utilisateur connecté
    Route::get('tickets', [TicketController::class, 'index']);

    // Routes afficher les evenements creer par l'utilisateur connecté
    Route::get('/events/my-events', [EventController::class, 'myEvents'])->name('events.my-events');

    // Route pour scanner le QR code
    Route::post('tickets/scan/{ticket}', [TicketController::class, 'scanTicket']);

    //  Route poour afficher le dashboard
});

// Route dans le backend
Route::get('/events/{id}/dashboard', [EventController::class, 'dashboard']);

// Route publique pour les catégories
Route::apiResource('categories', CategoryController::class)->except(['store', 'update', 'destroy']);

// Routes publiques pour les billets (Création)
Route::post('tickets', [TicketController::class, 'store']);

// Routes publiques pour les  événements (Voir liste, voir un élément)
Route::apiResource('events', EventController::class)->only(['index', 'show']);

// Routes public pout voir les categories des evenements
Route::apiResource('categorieEvents', EventController::class)->only(['index', 'show']);
Route::get('/events/{id}/similar', [EventController::class, 'similarEvents']);

Route::get('categories/{category}', [CategoryController::class, 'getCategoryEventAssociations']);
Route::post('/tickets/webhook', [TicketController::class, 'webhook'])->name('tickets.webhook');
Route::get('tickets/{ticket}', [TicketController::class, 'show']);
// Route::get('/events?category=${categoryId}', [EventController::class, 'index']);
    // Routes pour les portefeuilles (Création, Modification, Suppression)
    Route::apiResource('wallets', WalletController::class);

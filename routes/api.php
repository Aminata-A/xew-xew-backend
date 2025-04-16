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

/*
|--------------------------------------------------------------------------
| Routes API
|--------------------------------------------------------------------------
*/

// ✅ Routes d'authentification publiques avec préfixe clair et noms définis
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthentificationController::class, 'register'])->name('auth.register');
    Route::post('/login', [AuthentificationController::class, 'login'])->name('auth.login');
    Route::post('/verify-email', [VerifyEmailController::class, 'verifyEmail'])->name('auth.verify.email');
    Route::post('/verify-code', [VerifyCodeController::class, 'verifyCode'])->name('auth.verify.code');

    // ✅ Routes protégées avec middleware
    Route::middleware('auth:api')->group(function () {
        Route::get('/profile', [AuthentificationController::class, 'getUserProfile'])->name('auth.profile');
        Route::put('/profile', [AuthentificationController::class, 'updateProfile'])->name('auth.profile.update');
        Route::post('/logout', [AuthentificationController::class, 'logout'])->name('auth.logout');
    });
});

// ✅ Routes publiques pour les événements (voir tous ou un)
Route::apiResource('events', EventController::class)->only(['index', 'show']);

// ✅ Route publique : voir événements similaires
Route::get('/events/{id}/similar', [EventController::class, 'similarEvents'])->name('events.similar');

// ✅ Route publique : dashboard d’un événement
Route::get('/events/{id}/dashboard', [EventController::class, 'dashboard'])->name('events.dashboard');

// ✅ Routes publiques pour les catégories (liste et détail uniquement)
Route::apiResource('categories', CategoryController::class)->except(['store', 'update', 'destroy']);
Route::get('categories/{category}', [CategoryController::class, 'getCategoryEventAssociations'])->name('categories.event-associations');

// ✅ Route publique : événements liés à une catégorie (dupliquée ici, à harmoniser plus tard)
Route::apiResource('categorieEvents', EventController::class)->only(['index', 'show'])->names('category.events');

// ✅ Routes publiques pour les tickets
Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store');
Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
Route::post('/tickets/webhook', [TicketController::class, 'webhook'])->name('tickets.webhook');

// ✅ Routes pour les statistiques d’événement
Route::get('events/{eventId}/statistics', [TicketController::class, 'getEventStatistics'])->name('events.statistics');

// ✅ Routes pour les portefeuilles
Route::apiResource('wallets', WalletController::class);

// ✅ Routes protégées par token (auth:api)
Route::middleware('auth:api')->group(function () {
    // 🎫 Tickets liés à l’utilisateur connecté
    Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');

    // 📅 Événements créés par l’utilisateur
    Route::get('/events/my-events', [EventController::class, 'myEvents'])->name('events.my-events');

    // 🎫 Scanner un ticket via QR code
    Route::post('tickets/scan/{ticket}', [TicketController::class, 'scanTicket'])->name('tickets.scan');

    // 📦 Gestion des événements (CRUD partiel + archive)
    Route::apiResource('events', EventController::class)->only(['store', 'destroy']);

    Route::post('events/{event}/update', [EventController::class, 'update'])->name('events.update');
    Route::post('events/{event}/restore', [EventController::class, 'restore'])->name('events.restore');
    Route::delete('events/{event}/force-destroy', [EventController::class, 'forceDestroy'])->name('events.force-destroy');
    Route::get('events/trash', [EventController::class, 'trash'])->name('events.trash');

    // 🗂 Gestion protégée des catégories
    Route::apiResource('categories', CategoryController::class)->only(['store', 'update', 'destroy']);
});

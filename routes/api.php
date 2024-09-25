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


// Routes pour les authentifications
Route::middleware('auth:sanctum')->prefix('auth:api')->group(function () {
    // Routes pour la verification de l'email
    Route::post('verify-email', [VerifyEmailController::class, 'verify']);
    // Routes pour la verification du code
    Route::post('verify-code', [VerifyCodeController::class, 'verifyCode']);
    // Routes pour l'inscription de l'utilisateur
    Route::post('register', [AuthentificationController::class, 'register'])->name('register');
    // Routes pour la connexion de l'utilisateur
    Route::post('login', [AuthentificationController::class, 'login'])->name('login');
});

// Routes protegées par le token
Route::middleware('auth:api')->group(function () {
    // Routes pour les evenements (Creation, Modification, Suppression)
    Route::apiResource('events', EventController::class)->only(['store', 'update', 'destroy']);
    // Routes pour la restauration d'un evenement supprimé
    Route::post('events/{event}/restore', [EventController::class, 'restore']);
    // Routes pour la suppression definitive d'un evenement
    Route::post('events/{event}/force-destroy', [EventController::class, 'forceDestroy']);
    // Routes pour l'archivage d'un evenement
    Route::get('events/trash', [EventController::class, 'trash']);
    // Routes pour les categories
    Route::apiResource('categories', CategoryController::class);
    // Routes pour les porte feuilles(creation, Modification, suppression)
    Route::resource( 'wallets', WalletController::class);
});

// Routes pour les billets (creation)
Route::post('tickets', [TicketController::class, 'store']);
// Routes pour les evenements (lister, voir)
Route::apiResource('events', EventController::class)->only(['index', 'show']);


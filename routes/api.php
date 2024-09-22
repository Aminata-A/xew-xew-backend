<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EventController;
use App\Http\Controllers\Auth\VerifyCodeController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Auth\AuthentificationController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


// Routes pour les authentifications
Route::prefix('auth')->group(function () {
    // Routes pour la verification de l'email
    Route::post('verify-email', [VerifyEmailController::class, 'verify']);
    // Routes pour la verification du code
    Route::post('verify-code', [VerifyCodeController::class, 'verifyCode']);
    // Routes pour l'inscription de l'utilisateur
    Route::post('register', [AuthentificationController::class, 'register']);
    // Routes pour la connexion de l'utilisateur
    Route::post('login', [AuthentificationController::class, 'login']);
});

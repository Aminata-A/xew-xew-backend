<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\VerifyCodeController;
use App\Http\Controllers\Auth\VerifyEmailController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Routes pour les authentifications
Route::prefix('auth')->group(function () {
    // Routes pour la verification de l'email
    Route::post('verify-email', [VerifyEmailController::class, 'verify']);
    Route::post('verify-code', [VerifyCodeController::class, 'verifyCode']);
});

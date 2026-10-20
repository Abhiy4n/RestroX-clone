<?php

use App\Http\Controllers\Api\CurrentUserController;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\LogoutController;
use App\Http\Controllers\Api\NewPasswordController;
use App\Http\Controllers\Api\PasswordResetLinkController;
use App\Http\Controllers\Api\RegisterController;
use App\Http\Controllers\Api\UserSessionController;
use App\Http\Controllers\Api\VerifyEmailController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:register');
Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
Route::get('/email/verify/{id}/{hash}', [VerifyEmailController::class, 'show'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:password-recovery');
Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:password-recovery');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [CurrentUserController::class, 'show']);
    Route::get('/sessions', [UserSessionController::class, 'index']);
    Route::get('/wallet', [WalletController::class, 'show']);
    Route::get('/wallet/transactions', [WalletController::class, 'transactions']);
    Route::post('/wallet/add-money', [WalletController::class, 'addMoney'])->middleware('throttle:wallet-mutations');
    Route::post('/wallet/send-money', [WalletController::class, 'sendMoney'])->middleware('throttle:wallet-mutations');
    Route::post('/logout', [LogoutController::class, 'destroy']);
});

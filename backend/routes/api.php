<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/me', [AuthController::class, 'me'])->name('me');

    Route::get('/wallets/my-balance', [WalletController::class, 'myBalance'])->name('wallets.my-balance');
    Route::get('/wallets/my-transactions', [WalletController::class, 'myTransactions'])->name('wallets.my-transactions');
    Route::get('/wallets/my-statistics', [WalletController::class, 'myStatistics'])->name('wallets.my-statistics');

    Route::get('/wallets', [WalletController::class, 'index'])->name('wallets.index');
    Route::post('/wallets', [WalletController::class, 'store'])->name('wallets.store');
    Route::get('/wallets/{wallet}', [WalletController::class, 'show'])->name('wallets.show');
    Route::get('/wallets/{wallet}/balance', [WalletController::class, 'balance'])->name('wallets.balance');
    Route::post('/wallets/{wallet}/activate', [WalletController::class, 'activate'])->name('wallets.activate');
    Route::post('/wallets/{wallet}/freeze', [WalletController::class, 'freeze'])->name('wallets.freeze');
    Route::post('/wallets/{wallet}/unfreeze', [WalletController::class, 'unfreeze'])->name('wallets.unfreeze');
    Route::post('/wallets/{wallet}/restrict', [WalletController::class, 'restrict'])->name('wallets.restrict');
    Route::post('/wallets/{wallet}/unrestrict', [WalletController::class, 'unrestrict'])->name('wallets.unrestrict');
    Route::post('/wallets/{wallet}/close', [WalletController::class, 'close'])->name('wallets.close');
    Route::post('/wallets/{wallet}/recharge', [WalletController::class, 'recharge'])->name('wallets.recharge');
    Route::get('/wallets/{wallet}/transactions', [WalletController::class, 'transactions'])->name('wallets.transactions');
    Route::get('/wallets/{wallet}/state-logs', [WalletController::class, 'stateLogs'])->name('wallets.state-logs');
    Route::get('/wallets/{wallet}/statistics', [WalletController::class, 'statistics'])->name('wallets.statistics');
});

<?php

use App\Http\Controllers\Api\OperationController;
use App\Http\Controllers\Operations\CryptoWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('webhook-verify')->group(function () {
    Route::post('/crypto/webhook', [CryptoWebhookController::class, 'handle']);
    Route::get('/internal/tron-wallets', [CryptoWebhookController::class, 'tronWallets']);
});

Route::middleware('merchant-api')->group(function () {
    Route::get('check/internal/tron-wallets', [CryptoWebhookController::class, 'tronWallets']);
    Route::post('check/crypto/webhook', [CryptoWebhookController::class, 'handle']);


    Route::get('/check', function () {
        return response()->json(['message' => 'Ok. Signature accepted.']);
    });

    Route::post('/exchange-rate', [OperationController::class,'exchange']);
    Route::post('/deposit',[OperationController::class,'deposit']);
    Route::post('/withdraw',[OperationController::class,'withdraw']);

});

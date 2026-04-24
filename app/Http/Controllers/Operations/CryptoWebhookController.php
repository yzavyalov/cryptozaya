<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\MerchantWallet;
use App\Models\Wallet;
use App\Services\Operations\CryptoWebhookService;
use Illuminate\Http\Request;


class CryptoWebhookController extends Controller
{
    public function __construct(CryptoWebhookService $cryptoWebhookService)
    {
        $this->cryptoWebhookService = $cryptoWebhookService;
    }

    public function handle(Request $request)
    {
        return $this->cryptoWebhookService->handle($request, 'tron');
    }

    public function tronWallets()
    {
        $wallets1 = Wallet::query()->where('network', 'tron')->pluck('number');

        $wallets2 = MerchantWallet::query()->where('network', 'tron')->pluck('number');

        return $wallets1->merge($wallets2)->values();
    }


    public function ethWallets()
    {
        $wallets1 = Wallet::query()->where('network', 'eth')->pluck('number');

        $wallets2 = MerchantWallet::query()->where('network', 'eth')->pluck('number');

        return $wallets1->merge($wallets2)->values();
    }


    public function ethHandle(Request $request)
    {
        return $this->cryptoWebhookService->handle($request, 'ethereum');
    }


}

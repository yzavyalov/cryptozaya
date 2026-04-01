<?php

namespace App\Services\Tron\WithdrawDeposits;

use App\Services\EncodeService;
use App\Services\Operations\MerchantWallet\MerchantWalletService;
use App\Services\Tron\TronService;

class SendTronDepositToMainWalletService
{
    public function __construct(TronService $tronService)
    {
        $this->tronService = $tronService;
    }
    public function send(array $balance, string $merchantMainWallet, string $depositWallet): void
    {
        $privateKey = MerchantWalletService::getPrivateKey($depositWallet);

        $map = [
            'TRX_balance' => 'TRX',
            'USDT_balance' => 'USDT',
            'USDC_balance' => 'USDC',
        ];

        foreach ($map as $balanceKey => $currency) {
            $amount = (float) ($balance[$balanceKey] ?? 0);

            if ($amount <= 0) {
                continue;
            }

            $this->tronService->send($currency, $privateKey, $merchantMainWallet, $amount);
        }
    }
}

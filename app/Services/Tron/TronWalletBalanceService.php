<?php

namespace App\Services\Tron;

class TronWalletBalanceService
{
    public function __construct(TronService $tronService)
    {
        $this->tronService = $tronService;
    }


    public function getBalance($wallet): array
    {
        return $this->tronService->getAllBalances($wallet);
    }

    public function getTRXBalance($balance): float
    {
        return $balance['balances']['TRX'] ?? 0;
    }

    public function getUSDTBalance($balance): float
    {
        return $balance['balances']['USDT'] ?? 0;
    }

    public function getUSDCBalance($balance): float
    {
        return $balance['balances']['USDC'] ?? 0;
    }

    public function hasTrc20Tokens($balance): bool
    {
        if ($this->getUSDTBalance($balance) > 0 || $this->getUSDCBalance($balance) > 0)
            return true;
        else
            return false;
    }


    public function checkBalance(string $wallet): array
    {
        $balance = $this->getBalance($wallet);

        $trxBalance = $this->getTRXBalance($balance);
        $usdtBalance = $this->getUSDTBalance($balance);
        $usdcBalance = $this->getUSDCBalance($balance);

        if (!$balance || ($trxBalance == 0.0 && $usdtBalance == 0.0 && $usdcBalance == 0.0)) {
            return [
                'need_commission' => false,
                'TRX_balance' => 0.0,
                'USDT_balance' => 0.0,
                'USDC_balance' => 0.0,
            ];
        }

        return [
            'need_commission' => $usdtBalance > 0 || $usdcBalance > 0 || $trxBalance > 0,
            'TRX_balance' => $trxBalance,
            'USDT_balance' => $usdtBalance,
            'USDC_balance' => $usdcBalance,
        ];
    }

}

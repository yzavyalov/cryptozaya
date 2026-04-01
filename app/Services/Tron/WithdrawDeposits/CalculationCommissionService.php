<?php

namespace App\Services\Tron\WithdrawDeposits;

use App\Services\Tron\TronWalletBalanceService;

class CalculationCommissionService
{
    public function __construct(TronWalletBalanceService $tronWalletBalanceService)
    {
        $this->tronWalletBalanceService = $tronWalletBalanceService;
    }

    public function calculateCommission(
        string $merchantMainWallet,
        string $walletMerchant,
        float $trxBalance,
        float $balanceUSDT,
        float $balanceUSDC,
    ): array {

        $tokens = ['USDT', 'USDC'];
        $commission = 0.0;
        $balance['USDT'] = $balanceUSDT;
        $balance['USDC'] = $balanceUSDC;

        foreach ($tokens as $token) {
            $tokenBalance = (float) ($balance[$token] ?? 0);

            if ($tokenBalance <= 0) {
                continue;
            }

            $fee = $this->tronWalletBalanceService
                ->tronService
                ->estimateTRC20Fee(
                    $token,
                    $walletMerchant,
                    $merchantMainWallet,
                    $tokenBalance
                );

            $commission += (float) ($fee['total_fee'] ?? 0);
        }

        $difference = $trxBalance - $commission;

        return [
            'enough_balance' => $difference >= 0,
            'commission' => $commission,
            'trx_balance' => $trxBalance,
            'difference' => $difference,
        ];
    }
}

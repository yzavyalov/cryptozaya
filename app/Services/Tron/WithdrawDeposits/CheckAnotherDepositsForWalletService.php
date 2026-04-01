<?php

namespace App\Services\Tron\WithdrawDeposits;

use App\Http\Enums\MerchantTransactionStatusEnum;

class CheckAnotherDepositsForWalletService
{
    public function checkDeposits($deposits, $wallet)
    {
        $anotherDeposits = $deposits->where('wallet_to', $wallet);

        foreach ($anotherDeposits as $deposit)
        {
            $deposit->update(['status' => MerchantTransactionStatusEnum::toMainWallet->value]);
        }
    }
}

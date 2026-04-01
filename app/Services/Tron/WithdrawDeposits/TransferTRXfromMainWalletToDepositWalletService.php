<?php

namespace App\Services\Tron\WithdrawDeposits;

use App\Models\MerchantWallet;
use App\Services\Operations\MerchantWallet\MerchantWalletService;
use App\Services\Tron\TronService;

class TransferTRXfromMainWalletToDepositWalletService
{
    public function __construct(TronService $tronService)
    {
        $this->tronService = $tronService;
    }
    public function TransferTRXfromMainWalletToDepositWallet(MerchantWallet $mainWallet, $depositWallet, $amount)
    {
        $privateKey = MerchantWalletService::getPrivateKey($mainWallet->number);

        $request = $this->tronService->send('TRX',$privateKey,$depositWallet,$amount);
    }

    public function changeStatusDeposit()
    {

    }

}

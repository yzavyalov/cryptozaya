<?php

namespace App\Services\Operations\MerchantWallet;

use App\Models\Merchanttransaction;

class MerchantTransactionService
{
    public function create($merchantId,
                           $typeTransactions,
                           $status,
                           $blockchain,
                           $walletFrom,
                           $walletTo,
                           $merchantUserId,
                           $merchantTransactionId,
                           $sum,
                           $currencyId,)
    {
        return Merchanttransaction::create([
            'merchant_id' => $merchantId,
            'type_transactions' => $typeTransactions,
            'status' => $status,
            'network' => $blockchain,
            'wallet_from' => $walletFrom,
            'wallet_to' => $walletTo,
            'merchant_system_user_id' => $merchantUserId,
            'merchant_system_transaction_id' => $merchantTransactionId,
            'sum' => $sum,
            'currency_id' => $currencyId,
        ]);
    }
}

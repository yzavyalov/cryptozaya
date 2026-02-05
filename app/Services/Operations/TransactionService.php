<?php

namespace App\Services\Operations;

use App\Models\Transaction;

class TransactionService
{
    public function create($blockchain, $from, $to, $amount, $currency_id)
    {
        return Transaction::create([
            'network' => $blockchain,
            'wallet_from' => $from,
            'wallet_to' => $to,
            'sum' => $amount,
            'currency_id' => $currency_id,
        ]);
    }
}

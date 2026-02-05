<?php

namespace App\Services\Operations;

use App\Models\UserBalance;

class BalanceService
{
    public function create($userId, $currencyID)
    {
        return UserBalance::create([
                    'user_id' => $userId,
                    'currency_id' => $currencyID,
                    'balance' => 0,
                ]);
    }


    public function topup($userId, $amount, $currencyID)
    {
        $balance = $this->userBalanceCurrency($userId,$currencyID);

        if (!$balance)
            $balance = $this->create($userId,$currencyID);

        $balance->balance = $balance->balance + $amount;

        $balance->save();

        return true;
    }


    public function reduction($userId, $amount, $currencyID)
    {
        $balance = $this->userBalanceCurrency($userId,$currencyID);

        if (!$balance)
            return false;

        if ($balance->balance < $amount)
            return false;

        $balance->balance = $balance->balance - $amount;

        $balance->save();

        return true;
    }

    public function userBalanceCurrency($userId,$currencyID)
    {
        return UserBalance::query()
            ->where('user_id',$userId)
            ->where('currency_id',$currencyID)
            ->first() ?? false;
    }
}

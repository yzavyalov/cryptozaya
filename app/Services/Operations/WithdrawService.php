<?php

namespace App\Services\Operations;

use App\Services\Tron\TronService;

class WithdrawService
{
    public function __construct(TronService $tronService)
    {
        $this->tronService = $tronService;
    }
    public function withdraw($amount, $currency_id)
    {

    }
}

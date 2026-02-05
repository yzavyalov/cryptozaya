<?php

namespace App\Services\Tron;

class TronHelper
{
    public static function trxToSun(string $trx): string
    {
        return bcmul($trx, '1000000', 0);
    }

    public static function getTronMessage($message): string
    {
        return hex2bin($message);
    }
}

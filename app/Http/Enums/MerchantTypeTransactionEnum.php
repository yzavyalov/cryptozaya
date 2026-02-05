<?php
namespace App\Http\Enums;

enum MerchantTypeTransactionEnum: int
{
    case deposit = 1;
    case withdraw = 2;

    public function label(): string
    {
        return match ($this) {
            self::deposit => 'deposit',
            self::withdraw => 'withdraw',
        };
    }

}

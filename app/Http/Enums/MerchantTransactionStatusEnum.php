<?php
namespace App\Http\Enums;

use function Laravel\Prompts\search;

enum MerchantTransactionStatusEnum: int
{
    case created = 1;
    case successful = 2;
    case canceled = 3;
    case withoutInitialization = 4;
    case paid = 5;

    public function label(): string
    {
        return match ($this) {
            self::created => 'created',
            self::successful => 'successful',
            self::canceled => 'canceled',
            self::withoutInitialization => 'withoutInitialization',
            self::paid => 'paid',
        };
    }

}

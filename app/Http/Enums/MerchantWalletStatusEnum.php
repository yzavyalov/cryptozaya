<?php

namespace App\Http\Enums;

enum MerchantWalletStatusEnum: int
{
    case DEPOSIT = 1;

    case WITHDRAW = 2;

    case MAIN = 3;
}

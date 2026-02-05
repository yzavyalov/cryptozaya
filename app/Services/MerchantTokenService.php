<?php

namespace App\Services;

class MerchantTokenService
{
    public function createToken()
    {
        return bin2hex(random_bytes(32));
    }
}

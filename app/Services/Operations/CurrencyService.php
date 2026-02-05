<?php

namespace App\Services\Operations;

use App\Models\Currency;

class CurrencyService
{
    public static function tronToken(string $token): ?int
    {
        return Currency::where('name', $token)->value('id');
    }


    public static function tronDBNameToken($tokenId)
    {
        return Currency::query()->where('id', $tokenId)->pluck('name')->first();
    }

    public static function blockchain(string $token): ?string
    {
        return Currency::where('name', $token)->value('network');
    }

    public static function curencyForTronBlockchain(string $token): ?string
    {
        switch ($token) {
            case 'TRX': return 'TRX';
            case 'USDT (trc20)': return 'USDT';
            case 'USDC (trc20)': return 'USDC';
            default: return null;
        }
    }
}

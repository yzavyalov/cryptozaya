<?php

namespace App\Services;

use App\Models\Wallet;
use App\Models\WalletBalance;
use Illuminate\Support\Facades\Auth;

class WalletService
{
    protected function checkWallet($blockchain ,$adres)
    {
        return Wallet::query()
            ->where('network',$blockchain)
            ->where('number',$adres)
            ->exists();
    }


    public function createWallet($blockchain, $address, $hex=null, $publickey = null,$privatkey = null,$userId = null)
    {
        $userId = $userId ?? Auth::id();

        $existing = Wallet::where('network', $blockchain)
            ->where('number', $address)
            ->first();

        if ($existing)
        {
            return $existing;
        }

        return Wallet::create([
            'user_id' => $userId,
            'number' => $address,
            'hex' => $hex,
            'network' => $blockchain,
            'publicKey' => EncodeService::encrypte($publickey),
            'privateKey' => EncodeService::encrypte($privatkey),
        ]);
    }


    public function checkUserWallet($blockchain, $userId = null)
    {
        $userId = $userId ?? Auth::id();

        $wallets = Wallet::where('user_id', $userId)
            ->where('network', $blockchain)
            ->get();

        return $wallets->isNotEmpty() ? $wallets : false;
    }


}

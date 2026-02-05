<?php

namespace App\Services\Operations\MerchantWallet;

use App\Http\Enums\BlockChainEnum;
use App\Models\Merchant;
use App\Models\MerchantWallet;
use App\Services\EncodeService;
use App\Services\Tron\TronService;

class MerchantWalletService
{
    public function __construct(TronService $tronService)
    {
        $this->tronService = $tronService;
    }

    public function create($data)
    {
        $wallet = $this->tronService->createWallet();

        $a = json_encode($wallet['encrypted_private_key']);
        $b = EncodeService::encrypte($a);

        return MerchantWallet::create([
            'merchant_user_id' => $data['user_id'] ?? null,
            'merchant_transaction_id' => $data['transaction_id'] ?? null,
            'merchant_id' => $data['merchant_id'],
            'number' => $wallet['address'],
            'hex' => $wallet['hex'],
            'network' => 'tron',
            'public_key' => $wallet['publicKey'],
            'private_key' => isset($wallet['encrypted_private_key'])
                                ? $b
                                : null,
        ]);
    }

    public function selectMerchantWalletForWithdraw(Merchant $merchant, $amount, $currency)
    {
        $merchantWallet = $merchant->withDrawWallet();

        $walletBalance = $this->tronService->getAllBalances($merchantWallet->number); //здесь кошелек мерчанта для списания

//        if ($walletBalance['balances'][BlockChainEnum::exchangeCurrency($currency)] > $amount)
            return $merchantWallet;

//        return null;
    }

    public static function getHexWallet(string $address)
    {
        return MerchantWallet::query()->where('number', $address)->pluck('hex')->first();
    }

    public static function getPrivateKey(string $address)
    {
        return MerchantWallet::query()->where('number', $address)->pluck('private_key')->first();

    }
}

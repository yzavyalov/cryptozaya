<?php

namespace App\Services\Operations\MerchantWallet;

use App\Http\Enums\BlockChainEnum;
use App\Models\Merchant;
use App\Models\MerchantWallet;
use App\Services\EncodeService;
use App\Services\Ethereum\EthereumService;
use App\Services\Operations\CurrencyService;
use App\Services\Tron\TronService;

class MerchantWalletService
{
    public function __construct(TronService $tronService, EthereumService $ethereumService)
    {
        $this->tronService = $tronService;

        $this->ethereumService = $ethereumService;
    }

    public function create($data)
    {
        $blockchain = CurrencyService::blockchain($data['currency']);

        switch ($blockchain) {
            case 'tron': $service = $this->tronService; break;
            case 'ethereum': $service = $this->ethereumService; break;
            default: return false;
        }

        $wallet = $service->createWallet();

        $walletData = $wallet['data'] ?? $wallet;

        $a = json_encode($walletData['encrypted_private_key']);
        $b = EncodeService::encrypte($a);

        return MerchantWallet::create([
            'merchant_user_id' => $data['user_id'] ?? null,
            'merchant_transaction_id' => $data['transaction_id'] ?? null,
            'merchant_id' => $data['merchant_id'],
            'number' => $walletData['address'],
            'hex' => $walletData['hex'],
            'network' => $blockchain,
            'public_key' => $walletData['publicKey'],
            'private_key' => isset($walletData['encrypted_private_key'])
                                ? $b
                                : null,
        ]);
    }

    public function selectMerchantWalletForWithdraw(Merchant $merchant, $amount, $currency)
    {
        $network = CurrencyService::blockchain($currency);

        $merchantWallet = $merchant->withDrawWallet()->where('network', $network)->first();

        $service = match ($network) {
            'tron' => $this->tronService,
            'ethereum' => $this->ethereumService,
            default => null,
        };

        if ($service === null) {
            return response()->json([
                'status' => 'error',
                'code' => 'UNSUPPORTED_NETWORK',
                'message' => 'Unsupported blockchain network',
            ], 422);
        }

        $walletBalance = $service->getAllBalances($merchantWallet->number); //здесь кошелек мерчанта для списания

        if ($walletBalance['balances'][BlockChainEnum::exchangeCurrency($currency)] > $amount)
            return $merchantWallet;
        else
            return null;
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

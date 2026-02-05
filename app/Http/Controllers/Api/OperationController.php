<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Enums\BlockChainEnum;
use App\Http\Enums\MerchantTransactionStatusEnum;
use App\Http\Enums\MerchantTypeTransactionEnum;
use App\Http\Requests\Api\DepositRequest;
use App\Http\Requests\Api\ExchangeRequest;
use App\Http\Requests\Api\WithdrawRequest;
use App\Services\Operations\CurrencyService;
use App\Services\Operations\MerchantWallet\MerchantTransactionService;
use App\Services\Operations\MerchantWallet\MerchantWalletService;
use App\Services\Tron\ExchangeTronCoinGekoService;
use App\Services\Tron\TronService;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Exceptions\TronSendMoneyException;

class OperationController extends Controller
{
    public function __construct(MerchantWalletService $merchantWalletService,
                                MerchantTransactionService $merchantTransactionService,
                                ExchangeTronCoinGekoService $exchangeTronCoinGekoService,
                                TronService $tronService)
    {
        $this->merchantWalletService = $merchantWalletService;

        $this->merchantTransactionService = $merchantTransactionService;

        $this->exchangeTronCoinGekoService = $exchangeTronCoinGekoService;

        $this->tronService = $tronService;
    }
    public function deposit(DepositRequest $request)
    {
        $validated = $request->validated();

        $validated['merchant_id'] = $request->attributes->get('merchant')->id;

        if (isset($validated['currency_to']) && !empty($validated['currency_to']))
        {
            $validated['amount'] = $validated['amount'] * $this->exchangeTronCoinGekoService->getExchangeRate($validated['currency'], $validated['currency_to']);

            $validated['currency'] = $validated['currency_to'];
        }

        $wallet = $this->merchantWalletService->create($validated);

        $this->merchantTransactionService->create($validated['merchant_id'],
                                                  MerchantTypeTransactionEnum::deposit,
                                                  MerchantTransactionStatusEnum::created,
                                                   CurrencyService::blockchain($validated['currency']),
                                          null,
                                                    $wallet['number'],
                                                    $validated['user_id'],
                                                    $validated['transaction_id'],
                                                    $validated['amount'],
                                                    CurrencyService::tronToken($validated['currency']),
        );

        return response()->json([
            'address' => $wallet['number'],
            'qr' => (string) QrCode::generate($wallet['number']),
            'user_id' => $validated['user_id'],
            'transaction_id' => $validated['transaction_id'],
            'token' => $validated['currency'],
            'amount' => $validated['amount'],
            'currency' => $validated['currency_to'] ?? $validated['currency'],
        ]);

        //делаем запрос на создание кошелька и возвращаем кошелек, сумму, юзера, и транзакцию и qr-код
    }

    public function withdraw(WithdrawRequest $request)
    {
        $validated = $request->validated();

        if (isset($validated['currency_to']) && !empty($validated['currency_to']))
        {
            $validated['amount'] = $validated['amount'] * $this->exchangeTronCoinGekoService->getExchangeRate($validated['currency'], $validated['currency_to']);

            $validated['currency'] = $validated['currency_to'];
        }

        $merchant = $request->attributes->get('merchant');

        $merchantWallet = $this->merchantWalletService->selectMerchantWalletForWithdraw($merchant, $validated['amount'], $validated['currency']);

        if($merchantWallet === null)
            return response()->json(['error' => 'Not enough balance'], 400);

        $merchantTransaction = $this->merchantTransactionService->create($merchant->id,
                                                            MerchantTypeTransactionEnum::withdraw,
                                                            MerchantTransactionStatusEnum::created,
                                                            'tron',
                                                            $merchantWallet->number,
                                                            $validated['address'],
                                                            $validated['user_id'],
                                                            $validated['transaction_id'],
                                                            $validated['amount'],
                                                            CurrencyService::tronToken($validated['currency']));

        try {
            $result = $this->tronService->send(CurrencyService::curencyForTronBlockchain($validated['currency']),$merchantWallet->private_key,$validated['address'],$validated['amount']);

            $merchantTransaction->update(['status' => MerchantTransactionStatusEnum::successful]);

            return response()->json([
                'status' => 'ok',
                'txHash' => $result['data']['txHash'],
            ]);

        } catch (TronSendMoneyException $e) {

            $merchantTransaction->update(['status' => MerchantTransactionStatusEnum::canceled]);

            return response()->json([
                'status' => 'error',
                'code' => $e->getCodeName(),
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function exchange(ExchangeRequest $request)
    {
        $validated = $request->validated();

        return $this->exchangeTronCoinGekoService->getExchangeRate($validated['from'], $validated['to']);
    }
}

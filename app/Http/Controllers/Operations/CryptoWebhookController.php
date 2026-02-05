<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Enums\MerchantTransactionStatusEnum;
use App\Http\Enums\MerchantTypeTransactionEnum;
use App\Models\Merchanttransaction;
use App\Models\MerchantWallet;
use App\Models\Wallet;
use App\Services\Operations\BalanceService;
use App\Services\Operations\CurrencyService;
use App\Services\Operations\MerchantWallet\MerchantTransactionService;
use App\Services\Operations\MerchantWallet\MerchantWebhookService;
use App\Services\Operations\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;


class CryptoWebhookController extends Controller
{
    public function __construct(BalanceService $balanceService,
                                TransactionService $transactionService,
                                MerchantTransactionService $merchantTransactionService,
                                MerchantWebhookService $merchantWebHookService)
    {
        $this->balanceService = $balanceService;

        $this->transactionService = $transactionService;

        $this->merchantTransactionService = $merchantTransactionService;

        $this->merchantWebHookService = $merchantWebHookService;
    }

    public function handle(Request $request)
    {
        $raw = $request->getContent();

        // --- Декодируем JSON в массив ---
        $data = json_decode($raw, true);

        if (!$data) {
            return response()->json(['error' => 'Invalid JSON'], 400);
        }

        // --- Валидация данных ---
        $validated = Validator::make($data, [
            'txid'   => 'required|string|max:100|unique:tron_deposits,transaction_id',
            'type'   => 'required|string',
            'from'   => 'required|string|max:35',
            'to'     => 'required|string|max:35',
            'amount' => 'required|numeric|min:0.000001',
            'block'  => 'required|integer|min:0',
        ])->validate(); // ->validate() автоматически выбросит ValidationException, если что-то не так

        // --- Логируем проверенные данные ---
        Log::info('Webhook received', $validated);


        // --- Проверка, принадлежит ли адрес нам ---
        $wallet = Wallet::query()->where('number', $validated['to'])->first();

        $merchantWallet = MerchantWallet::query()->where('number', $validated['to'])->first();

        if (!$wallet && !$merchantWallet) {
            return response()->json(['ignored' => true]);
        }
Log::info('wallet found', compact('wallet'));

        // --- Логика депозита ---
        $this->transactionService->create(
            'tron',
            $validated['from'],
            $validated['to'],
            $validated['amount'],
            CurrencyService::tronToken($validated['type'])
        );
Log::info('transaction created', $validated);


        if ($merchantWallet)
        {
            $merchantTransactions = Merchanttransaction::query()->where('wallet_to',$data['to'])
                                                                ->where('sum',$data['amount'])
                                                                ->where('status', MerchantTransactionStatusEnum::created)
                                                                ->first();
            Log::info('merchant transaction found', compact('merchantTransactions'));
            if ($merchantTransactions) {
                $merchantTransactions->update(['status' => MerchantTransactionStatusEnum::successful->value]);
                Log::info('merchant transaction updated', compact('merchantTransactions'));
            }
            else{
                $merchantTransaction = $this->merchantTransactionService->create($merchantWallet->merchant_id,
                                            MerchantTypeTransactionEnum::deposit->value,
                                                    MerchantTransactionStatusEnum::withoutInitialization->value,
                                                'tron',
                                                          $validated['from'],
                                                          $validated['to'],
                                                          $merchantWallet->merchant_user_id,
                                                          $merchantWallet->merchant_transaction_id,
                                                          $validated['amount'],
                                                          CurrencyService::tronToken($validated['type']));

                Log::info('merchant transaction created 2', compact('merchantTransactions'));
            }

            $merchant = $merchantWallet->merchant;

            if (!empty($merchant->cburl))
                $this->merchantWebHookService->sendWebhook($merchantTransactions);
        }






Log::info('deposit', $data);
        return response()->json(['ok' => true]);
    }

    public function tronWallets()
    {
        $wallets1 = Wallet::query()->where('network', 'tron')->pluck('number');

        $wallets2 = MerchantWallet::query()->where('network', 'tron')->pluck('number');

        return $wallets1->merge($wallets2)->values();
    }


}

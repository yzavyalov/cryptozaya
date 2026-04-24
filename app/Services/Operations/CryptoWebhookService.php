<?php

namespace App\Services\Operations;

use App\Http\Enums\MerchantTransactionStatusEnum;
use App\Http\Enums\MerchantTypeTransactionEnum;
use App\Models\Merchanttransaction;
use App\Models\MerchantWallet;
use App\Models\Wallet;
use App\Services\Operations\MerchantWallet\MerchantTransactionService;
use App\Services\Operations\MerchantWallet\MerchantWebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CryptoWebhookService
{
    public function __construct(
        protected TransactionService $transactionService,
        protected MerchantTransactionService $merchantTransactionService,
        protected MerchantWebhookService $merchantWebHookService,
    ) {}

    public function handle(Request $request, string $network)
    {
        $raw = $request->getContent();

        $data = json_decode($raw, true);

        if (!$data) {
            return response()->json(['error' => 'Invalid JSON'], 400);
        }

        $validated = Validator::make($data, [
            'txid'   => 'required|string|max:100',
            'type'   => 'required|string',
            'from'   => 'required|string|max:100',
            'to'     => 'required|string|max:100',
            'amount' => 'required|numeric|min:0.000001',
            'block'  => 'required|integer|min:0',
        ])->validate();

        $wallet = Wallet::query()
            ->where('number', $validated['to'])
            ->first();

        $merchantWallet = MerchantWallet::query()
            ->where('number', $validated['to'])
            ->first();

        if (!$wallet && !$merchantWallet) {
            return response()->json(['ignored' => true]);
        }

        $currency = CurrencyService::tronToken($validated['type']);

        $this->transactionService->create(
            $network,
            $validated['from'],
            $validated['to'],
            $validated['amount'],
            $currency
        );

        if ($merchantWallet) {
            $merchantTransaction = Merchanttransaction::query()
                ->where('wallet_to', $validated['to'])
                ->where('sum', $validated['amount'])
                ->where('status', MerchantTransactionStatusEnum::created->value)
                ->first();

            if ($merchantTransaction) {
                $merchantTransaction->update([
                    'status' => MerchantTransactionStatusEnum::successful->value,
                ]);
            } else {
                $merchantTransaction = Merchanttransaction::query()
                    ->where('wallet_to', $validated['to'])
                    ->where('sum', $validated['amount'])
                    ->where('status', MerchantTransactionStatusEnum::toMainWallet->value)
                    ->first();

                if ($merchantTransaction) {
                    $merchantTransaction->update([
                        'status' => MerchantTransactionStatusEnum::paid->value,
                    ]);
                } else {
                    $merchantTransaction = $this->merchantTransactionService->create(
                        $merchantWallet->merchant_id,
                        MerchantTypeTransactionEnum::deposit->value,
                        MerchantTransactionStatusEnum::withoutInitialization->value,
                        $network,
                        $validated['from'],
                        $validated['to'],
                        $merchantWallet->merchant_user_id,
                        $merchantWallet->merchant_transaction_id,
                        $validated['amount'],
                        $currency
                    );
                }
            }

            $merchant = $merchantWallet->merchant;

            if (!empty($merchant->cburl)) {
                $this->merchantWebHookService->sendWebhook(
                    $merchant,
                    $merchantTransaction?->fresh()?->toArray()
                );
            }
        }

        return response()->json(['ok' => true]);
    }

}

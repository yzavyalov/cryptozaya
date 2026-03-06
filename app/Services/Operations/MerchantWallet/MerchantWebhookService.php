<?php

namespace App\Services\Operations\MerchantWallet;

use App\Http\Enums\MerchantTransactionStatusEnum;
use App\Http\Enums\MerchantTypeTransactionEnum;
use App\Services\Operations\CurrencyService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class MerchantWebhookService
{
    public function sendWebhook($merchant, $merchantTransactions)
    {
        $data['id'] = $merchantTransactions['id'];
        $data['merchant'] = $merchant['name'];
        $data['type'] = MerchantTypeTransactionEnum::from($merchantTransactions['type_transactions'])->label();
        $data['type_id'] = $merchantTransactions['type_transactions'];
        $data['status'] = MerchantTransactionStatusEnum::from($merchantTransactions['status'])->label();
        $data['status_id'] = $merchantTransactions['status'];
        $data['network'] = $merchantTransactions['network'];
        $data['merchant_system_user_id'] = $merchantTransactions['merchant_system_user_id'];
        $data['merchant_system_transaction_id'] = $merchantTransactions['merchant_system_transaction_id'];
        $data['amount'] = $merchantTransactions['sum'];
        $data['currency'] = CurrencyService::tronDBNameToken($merchantTransactions['currency_id']);
        $data['signature'] = $merchant->token;

        return $this->sendRequest($merchant->cburl, $data);
    }



    protected function sendRequest(string $path, array $body = []): array
    {
        try {

            $signature = $body['signature'] ?? null;

            $response = Http::timeout(10)
                ->withHeaders([
                    'X-Signature' => $signature,
                ])
                ->post($path, $body);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'status'  => $response->status(),
                    'data'    => $response->json(),
                ];
            }

            // если 4xx / 5xx
            Log::error('Webhook request failed', [
                'path'     => $path,
                'body'     => $body,
                'status'   => $response->status(),
                'response' => $response->body(),
            ]);

            return [
                'success' => false,
                'status'  => $response->status(),
                'error'   => $response->body(),
            ];

        } catch (Throwable $e) {

            // если вообще network ошибка (DNS, timeout и тд)
            Log::error('Webhook request exception', [
                'path'  => $path,
                'body'  => $body,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status'  => null,
                'error'   => $e->getMessage(),
            ];
        }
    }

    protected function responseDataToMerch($type)
    {
        return json()->response([
            'type' => $type,  //deposit ir withdraw
            'amount' => $amount,
            ''
        ]);
    }


    public function sendExampleDepositCallback($merchant)
    {
        $exampleData = [
            'id' => rand(1, 1000),
            'merchant_id' => $merchant->id,
            'type_transactions' => MerchantTypeTransactionEnum::deposit,
            'status' => MerchantTransactionStatusEnum::successful,
            'network' => 'tron',
            'wallet_from' => Str::random(33),
            'wallet_to' => Str::random(33),
            'merchant_system_user_id' => Str::random(6),
            'merchant_system_transaction_id' => Str::random(12),
            'sum' => rand(100, 1000),
            'currency' => 'GBP',
        ];

        return $this->sendWebhook($merchant,$exampleData);
    }


    public function sendExampleWithdrawCallback($merchant)
    {
        $exampleData = [
            'id' => rand(1, 1000),
            'merchant_id' => $merchant->id,
            'type_transactions' => MerchantTypeTransactionEnum::withdraw->label(),
            'status' => MerchantTransactionStatusEnum::successful->label(),
            'network' => 'tron',
            'wallet_from' => Str::random(33),
            'wallet_to' => Str::random(33),
            'merchant_system_user_id' => Str::random(6),
            'merchant_system_transaction_id' => Str::random(12),
            'sum' => rand(100, 1000),
            'currency' => 'GBP',
        ];

        return $this->sendWebhook($merchant,$exampleData);
    }
}

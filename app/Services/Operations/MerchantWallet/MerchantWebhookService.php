<?php

namespace App\Services\Operations\MerchantWallet;

use App\Models\Merchant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MerchantWebhookService
{
    public function sendWebhook($merchantTransactions)
    {
        $merchant = Merchant::query()->findOrFail($merchantTransactions->merchant_id);

        $data['signature'] = hash('sha256', json_encode($merchant->name));

        $this->sendRequest($merchant->cburl, $data);
    }

    protected function sendRequest(string $path, array $body = [])
    {

        $signature = $body['signature'];

        // Отправка POST запроса с заголовком X-Signature
        $response = Http::withHeaders([
            'X-Signature' => $signature
        ])->post($path, $body);

        if ($response->failed()) {
            Log::error('Tron node request failed', [
                'path' => $path,
                'body' => $body,
                'status' => $response->status(),
                'response' => $response->body()
            ]);
            throw new \Exception("Tron node error: " . $response->body());
        }

        return $response->json();
    }

    protected function responseDataToMerch($type)
    {
        return json()->response([
            'type' => $type,  //deposit ir withdraw
            'amount' => $amount,
            ''
        ]);
    }
}

<?php

namespace App\Services\Tron;

use App\Exceptions\TronSendMoneyException;
use App\Services\EncodeService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TronService
{
    const BASE = 'https://api.trongrid.io';

    // TRC20 контракты Mainnet
    protected $tokens = [
        'USDT' => 'TXLAQ63Xg1NAzckPwKHvzw7CSEmLMEqcdj',
        'USDC' => 'TCFLL5dx5ZJdKnWuesXxi1VPwjLVmWZZy9',
        'TRX'  => null, // нативный токен TRX
    ];

    // ----------------- Helper: POST запрос с HMAC, timestamp и nonce -----------------
    protected function postToNode(string $path, array $body = [])
    {
        // Добавляем защиту от replay attack
        $body['timestamp'] = time();                  // текущее время в секундах
        $body['nonce'] = uniqid('', true);           // уникальный идентификатор запроса

        // Генерация HMAC подписи
        $jsonBody = json_encode($body);
        $signature = hash_hmac('sha256', $jsonBody, env('WEBHOOK_SECRET'));

        // Отправка POST запроса с заголовком X-Signature
        $response = Http::withHeaders([
            'X-Signature' => $signature
        ])->post(env('TRON_SERVICE_URL') . $path, $body);

        if ($response->failed()) {
            Log::error('Tron node request failed', [
                'path' => $path,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            throw new TronSendMoneyException(
                'Blockchain service unavailable',
                'NODE_UNAVAILABLE'
            );
        }

        $data = $response->json();

        if (!($data['success'] ?? true)) {
            throw new TronSendMoneyException(
                $data['error']['message'] ?? 'Transaction rejected',
                $data['error']['code'] ?? 'TRON_REJECTED'
            );
        }


        return $data;
    }

    // ----------------- Helper: GET запрос -----------------
    protected function getFromNode(string $path)
    {
        $timestamp = time();
        $data = ''; // GET без body → data пустая строка

        // 🔐 HMAC должен совпадать с verifyHmac.mjs
        $signature = hash_hmac(
            'sha256',
            $timestamp . $data,
            env('WEBHOOK_SECRET')
        );

        $url = env('TRON_SERVICE_URL') . $path;

        $response = Http::get($url, [
            'timestamp' => $timestamp,
            'data' => $data,
            'signature' => $signature,
        ]);

        if ($response->failed()) {
            Log::error('Tron node GET failed', [
                'path' => $path,
                'timestamp' => $timestamp,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            throw new \Exception("Tron node GET error: " . $response->body());
        }

        return $response->json();
    }



    // ----------------- Wallet -----------------

    public function createWallet()
    {
        return $this->getFromNode('/wallet/create');
    }

    public function getAllBalances(string $address)
    {
        return $this->getFromNode("/wallet/{$address}/balances");
    }

    public function getTRXBalance(string $address)
    {
        return $this->getFromNode("/balance/trx/{$address}");
    }

    public function getTokenBalance(string $token, string $address)
    {
        $token = strtoupper($token);
        if (!isset($this->tokens[$token])) throw new \Exception("Unknown token {$token}");

        return $this->getFromNode("/balance/{$token}/{$address}");
    }

    // ----------------- Transactions -----------------

    public function estimateTRC20Fee(string $token, string $from, string $to, float $amount)
    {
        $token = strtoupper($token);

        if (!array_key_exists($token, $this->tokens)) {
            throw new \Exception("Unknown token {$token}");
        }

        return $this->postToNode('/estimate-fee', [
            'token' => $token,
            'from' => $from,
            'to' => $to,
            'amount' => $amount
        ]);
    }

    public function send($asset,$privateKey,$to,$amount)
    {
        $asset = strtoupper($asset);

        $decodedKey = EncodeService::decrypte($privateKey);
        // TRX — нативная монета
        if ($asset === 'TRX') {
            return $this->postToNode('/send/trx', [
                'privateKey' => $decodedKey,
                'to' => $to,
                'amount' => $amount
            ]);
        }

        // TRC20 токены
        if (!isset($this->tokens[$asset])) {
            throw new TronSendMoneyException(
                "Unknown token {$asset}",
                'UNKNOWN_TOKEN'
            );
        }

        return $this->postToNode("/send/{$asset}", [
            'privateKey' => $decodedKey,
            'to' => $to,
            'amount' => $amount
        ]);
    }


    // ----------------- Transactions History -----------------

    public function getTRC20Transactions(string $address, string $token = 'USDT')
    {
        $contract = $this->tokens[strtoupper($token)] ?? null;
        if (!$contract) throw new \Exception("Unknown token {$token}");

        $url = self::BASE."/v1/accounts/{$address}/transactions/trc20?limit=200&contract_address={$contract}";
        return Http::get($url)->json();
    }

    public function getTRXTransactions(string $address)
    {
        $url = self::BASE."/v1/accounts/{$address}/transactions?limit=200";
        return Http::get($url)->json();
    }


    protected function getTransactionInfo(string $txId): ?array
    {
        $response = Http::withHeaders([
            'TRON-PRO-API-KEY' => env('TRON_GRID_KEY'),
        ])->get('https://api.trongrid.io/wallet/gettransactioninfobyid', [
            'value' => $txId
        ]);

        // Проверка успешности запроса
        if (!$response->successful()) {
            return null;
        }

        return $response->json();
    }



    public function waitForTrxConfirmation(string $txId, int $timeout = 15): bool
    {
        for ($i = 0; $i < $timeout; $i++) {
            $tx = $this->getTransactionInfo($txId);

            if (!empty($tx) && ($tx['receipt']['result'] ?? '') === 'SUCCESS') {
                return true;
            }

            sleep(1);
        }

        return false;
    }



    protected function getAccount(string $address): ?array
    {
        $response = Http::withHeaders([
            'TRON-PRO-API-KEY' => env('TRON_GRID_KEY'),
        ])->get('https://api.trongrid.io/wallet/getaccount', [
            'address' => $address,
            'visible' => true,
        ]);

        if (!$response->successful()) {
            return null;
        }

        return $response->json();
    }

    public function isAccountActivated(string $address): bool
    {
        $account = $this->getAccount($address);

        return !empty($account['address']);
    }

}

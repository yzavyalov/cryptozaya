<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChaingatewayService
{
    protected string $baseUrl;
    protected string $apiToken;

    public function __construct()
    {
        $this->baseUrl = config('services.chaingateway.base_url', 'https://api.chaingateway.io/api/v2');
        $this->apiToken = config('services.chaingateway.token');
    }

    protected function request(string $method, string $path, array $data = null)
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->apiToken,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
                'X-Network'     => 'testnet',
            ])->{$method}($url, $data);

            if ($response->failed()) {
                Log::error("Chaingateway API error", [
                    'method' => $method,
                    'url'    => $url,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                throw new \Exception("Chaingateway API request failed: " . $response->body(), $response->status());
            }

            return $response->json();
        } catch (Throwable $e) {
            Log::error("ChaingatewayService exception", [
                'exception' => $e,
                'method'    => $method,
                'url'       => $url,
            ]);
            throw $e;
        }
    }

    // Пример: создать адрес (Ethereum, BSC, Polygon и т.д.)
    public function createAddress(string $blockchain, string $password): array
    {
        // e.g. $blockchain = 'ethereum', 'bsc', 'polygon', 'tron' и др.
        $path = "{$blockchain}/addresses";
        return $this->request('post', $path, [
            'password' => $password,
        ]);
    }

    // Пример: получить список адресов
    public function listAddresses(string $blockchain): array
    {
        $path = "{$blockchain}/addresses";
        return $this->request('get', $path);
    }

    // Пример: создать native-транзакцию (ETH, BNB, MATIC и т.п.) — отправка «родной» валюты
    public function sendTransaction(string $blockchain, array $payload): array
    {
        // payload: ['from' => ..., 'to' => ..., 'amount' => ..., 'password' => ...]
        $path = "{$blockchain}/transactions";
        return $this->request('post', $path, $payload);
    }

    // Пример: создать токен-транзакцию (ERC20, BEP20, TRC20 и пр.)
    public function sendTokenTransaction(string $blockchain, array $payload): array
    {
        // payload: должно соответствовать документации Chaingateway для токенов
        $path = "{$blockchain}/token-transactions";
        return $this->request('post', $path, $payload);
    }
}

<?php

namespace App\Services\Ethereum;

use App\Services\EncodeService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EthereumService
{
    protected array $tokens;

    public function __construct()
    {
        $this->tokens = [
            'USDT' => [
                'address' => config('services.ethereum.tokens.usdt'),
                'decimals' => 6,
            ],
            'USDC' => [
                'address' => config('services.ethereum.tokens.usdc'),
                'decimals' => 6,
            ],
        ];
    }


    protected function postToNode(string $path, array $body = [])
    {
        $body['timestamp'] = time();
        $body['nonce'] = uniqid('', true);

        $jsonBody = json_encode($body);
        $signature = hash_hmac('sha256', $jsonBody, config('services.ethereum.url'));

        $response = Http::withHeaders([
            'X-Signature' => $signature
        ])->post(config('services.ethereum.url') . $path, $body);

        if ($response->failed()) {
            Log::error('Ethereum node request failed', [
                'path' => $path,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            throw new \RuntimeException('Ethereum blockchain service unavailable');
        }

        $data = $response->json();

        if (!($data['ok'] ?? $data['success'] ?? true)) {
            throw new \RuntimeException(
                $data['error']['message']
                ?? $data['error']
                ?? 'Ethereum transaction rejected'
            );
        }

        return $data;
    }

    protected function getFromNode(string $path, array $query = [])
    {
        $timestamp = time();
        $data = '';

        $signature = hash_hmac(
            'sha256',
            $timestamp . $data,
            config('services.ethereum.url')
        );

        $url = config('services.ethereum.url') . $path;

        $response = Http::get($url, array_merge($query, [
            'timestamp' => $timestamp,
            'data' => $data,
            'signature' => $signature,
        ]));

        if ($response->failed()) {
            Log::error('Ethereum node GET failed', [
                'path' => $path,
                'timestamp' => $timestamp,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            throw new \RuntimeException("Ethereum node GET error: " . $response->body());
        }

        $json = $response->json();

        if (!($json['ok'] ?? $json['success'] ?? true)) {
            throw new \RuntimeException(
                $json['error']['message']
                ?? $json['error']
                ?? 'Ethereum GET request rejected'
            );
        }

        return $json;
    }

    public function createWallet()
    {
        return $this->postToNode('/wallet/create', []);
    }

    public function getAllBalances(string $address): array
    {
        $eth = $this->getETHBalance($address);

        $balances = [
            'ETH' => $eth['balance_eth'] ?? '0',
        ];

        foreach ($this->tokens as $symbol => $token) {
            if (empty($token['address'])) {
                $balances[$symbol] = '0';
                continue;
            }

            try {
                $raw = $this->erc20Balance($symbol, $address);
                $balances[$symbol] = $this->formatToken($raw, (int) $token['decimals']);
            } catch (\Throwable $e) {
                Log::warning('ERC20 balance read failed', [
                    'symbol' => $symbol,
                    'address' => $address,
                    'contract' => $token['address'],
                    'error' => $e->getMessage(),
                ]);

                $balances[$symbol] = '0';
            }
        }

        return ['balances' => $balances];
    }

    public function getETHBalance(string $address): array
    {
        $response = $this->getFromNode("/balance/{$address}");
        $data = $response['data'] ?? $response;

        return [
            'balance_eth' => (string) ($data['balance_eth'] ?? '0'),
            'balance_wei' => (string) ($data['balance_wei'] ?? '0'),
        ];
    }

    public function getTokenBalance(string $token, string $address): array
    {
        $token = strtoupper($token);

        if ($token === 'ETH') {
            return $this->getETHBalance($address);
        }

        if (!isset($this->tokens[$token])) {
            throw new \RuntimeException("Unknown token {$token}");
        }

        $tokenConfig = $this->tokens[$token];

        if (empty($tokenConfig['address'])) {
            return [
                'balance' => '0',
                'raw_balance' => '0',
                'decimals' => $tokenConfig['decimals'] ?? 18,
            ];
        }

        $raw = $this->erc20Balance($address, $tokenConfig['address']);

        return [
            'balance' => $this->formatToken($raw, (int) $tokenConfig['decimals']),
            'raw_balance' => (string) $raw,
            'decimals' => (int) $tokenConfig['decimals'],
        ];
    }

    /**
     * Получить raw ERC20 balance в минимальных единицах токена.
     *
     * ВАЖНО:
     * Этот метод предполагает, что в eth-gateway будет endpoint,
     * который умеет делать balanceOf(contract, wallet).
     *
     * Например:
     * GET /token-balance/{contract}/{wallet}
     * => { ok: true, data: { balance: "1500000" } }
     */
    public function erc20Balance(string $token, string $wallet): string
    {
        $response = $this->getFromNode("/token-balance/{$token}/{$wallet}");
        $data = $response['data'] ?? $response;

        return (string) (
            $data['balance']
            ?? $data['raw_balance']
            ?? $data['amount']
            ?? '0'
        );
    }

    public function formatToken(string $raw, int $decimals): string
    {
        $raw = trim((string) $raw);

        if ($raw === '' || $raw === '0') {
            return '0';
        }

        if (!function_exists('bcdiv')) {
            return (string) ((float) $raw / (10 ** $decimals));
        }

        $divider = bcpow('10', (string) $decimals, 0);
        $value = bcdiv($raw, $divider, $decimals);

        return rtrim(rtrim($value, '0'), '.') ?: '0';
    }

    public function estimateFee(string $asset, string $from, string $to, float $amount)
    {
        $asset = strtoupper($asset);

        if ($asset !== 'ETH' && !array_key_exists($asset, $this->tokens)) {
            throw new \RuntimeException("Unknown token {$asset}");
        }

        return $this->postToNode('/estimate-fee', [
            'asset' => $asset,
            'from' => $from,
            'to' => $to,
            'amount' => $amount
        ]);
    }

    public function send($asset, $privateKey, $to, $amount)
    {
        $asset = strtoupper($asset);
        $decodedKey = EncodeService::decrypte($privateKey);

        if ($asset === 'ETH') {
            return $this->postToNode('/send', [
                'privateKey' => $decodedKey,
                'to' => $to,
                'amount' => $amount
            ]);
        }

        if (!isset($this->tokens[$asset])) {
            throw new \RuntimeException("Unknown token {$asset}");
        }

        $token = $this->tokens[$asset];

        if (empty($token['address'])) {
            throw new \RuntimeException("Token contract for {$asset} is not configured");
        }

        return $this->postToNode('/send-token', [
            'asset' => $asset,
            'contract' => $token['address'],
            'privateKey' => $decodedKey,
            'to' => $to,
            'amount' => $amount
        ]);
    }

    public function getTransactionInfo(string $txHash): ?array
    {
        $response = $this->getFromNode("/tx/{$txHash}");

        return $response['data'] ?? $response;
    }

    public function waitForEthConfirmation(string $txHash, int $timeout = 60): bool
    {
        $startedAt = time();
        $attempt = 0;
        $sleepSeconds = 2;

        while ((time() - $startedAt) < $timeout) {
            $attempt++;

            try {
                $tx = $this->getTransactionInfo($txHash);

                Log::info('Checking ETH confirmation', [
                    'tx_hash' => $txHash,
                    'attempt' => $attempt,
                    'elapsed_seconds' => time() - $startedAt,
                    'response' => $tx,
                ]);

                if (!empty($tx)) {
                    $status = $tx['status'] ?? null;

                    if ($status === 'confirmed') {
                        Log::info('ETH confirmed', [
                            'tx_hash' => $txHash,
                            'attempt' => $attempt,
                            'elapsed_seconds' => time() - $startedAt,
                        ]);

                        return true;
                    }

                    if ($status === 'failed') {
                        Log::warning('ETH failed', [
                            'tx_hash' => $txHash,
                            'attempt' => $attempt,
                            'elapsed_seconds' => time() - $startedAt,
                            'response' => $tx,
                        ]);

                        return false;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Error while checking ETH confirmation', [
                    'tx_hash' => $txHash,
                    'attempt' => $attempt,
                    'elapsed_seconds' => time() - $startedAt,
                    'error' => $e->getMessage(),
                ]);
            }

            sleep($sleepSeconds);
        }

        Log::warning('ETH confirmation timeout reached', [
            'tx_hash' => $txHash,
            'timeout_seconds' => $timeout,
        ]);

        return false;
    }
}

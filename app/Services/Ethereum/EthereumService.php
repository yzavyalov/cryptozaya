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

    protected function sanitizeForLog(array $payload): array
    {
        $sanitized = $payload;

        foreach (['privateKey', 'private_key', 'encrypted_private_key'] as $key) {
            if (array_key_exists($key, $sanitized)) {
                $sanitized[$key] = '[hidden]';
            }
        }

        return $sanitized;
    }

    protected function extractErrorMessage($response, ?array $data = null, string $default = 'Ethereum blockchain service unavailable'): string
    {
        return (string) (
            $data['error']['message']
            ?? $data['error']
            ?? $response->body()
            ?? $default
        );
    }

    protected function postToNode(string $path, array $body = []): array
    {
        Log::info('Ethereum node POST request', ['path' => $path, 'body' => $body]);
        $body['timestamp'] = time();
        $body['nonce'] = uniqid('', true);

        $jsonBody = json_encode($body);
        $signature = hash_hmac('sha256', $jsonBody, config('services.ethereum.url'));

        $response = Http::withHeaders([
            'X-Signature' => $signature,
        ])->post(config('services.ethereum.url') . $path, $body);

        $data = $response->json();

        Log::info('Ethereum node POST response', [
            'path' => $path,
            'status' => $response->status(),
            'request' => $this->sanitizeForLog($body),
            'response' => $data ?? $response->body(),
        ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                $this->extractErrorMessage($response, $data, 'Ethereum blockchain service unavailable')
            );
        }

        if (!($data['ok'] ?? $data['success'] ?? true)) {
            throw new \RuntimeException(
                $data['error']['message']
                ?? $data['error']
                ?? 'Ethereum transaction rejected'
            );
        }

        return $data ?? [];
    }

    protected function getFromNode(string $path, array $query = []): array
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

        $json = $response->json();

        Log::info('Ethereum node GET response', [
            'path' => $path,
            'status' => $response->status(),
            'query' => $query,
            'response' => $json ?? $response->body(),
        ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                $this->extractErrorMessage($response, $json, 'Ethereum node GET failed')
            );
        }

        if (!($json['ok'] ?? $json['success'] ?? true)) {
            throw new \RuntimeException(
                $json['error']['message']
                ?? $json['error']
                ?? 'Ethereum GET request rejected'
            );
        }

        return $json ?? [];
    }

    public function createWallet(): array
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
                $raw = $this->erc20Balance($token['address'], $address);
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

        $raw = $this->erc20Balance($tokenConfig['address'], $address);

        return [
            'balance' => $this->formatToken($raw, (int) $tokenConfig['decimals']),
            'raw_balance' => (string) $raw,
            'decimals' => (int) $tokenConfig['decimals'],
        ];
    }

    public function erc20Balance(string $contract, string $wallet): string
    {
        $response = $this->getFromNode("/token-balance/{$contract}/{$wallet}");
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

    public function send(string $asset, string $privateKey, string $to, string $amount): array
    {
        $asset = strtoupper($asset);
        $decodedKey = EncodeService::decrypte($privateKey);

        if (empty($decodedKey)) {
            throw new \RuntimeException('Private key could not be decrypted.');
        }

        if ($asset === 'ETH') {
            return $this->postToNode('/send', [
                'encrypted_private_key' => $decodedKey,
                'to' => $to,
                'amount' => $amount,
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
            'amount' => $amount,
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


    //estimate
    public function estimateFee(string $asset, string $from, string $to, float|string $amount): array
    {
        if ($asset === 'ETH') {
            Log::info('estimateFee ETH', ['asset' => $asset, 'from' => $from, 'to' => $to, 'amount' => $amount]);
            return $this->postToNode('/estimate-fee', [
                'from' => $from,
                'to' => $to,
                'value' => (string) $amount,
                'data' => '0x',
            ]);
        }

        if (!isset($this->tokens[$asset])) {
            throw new \RuntimeException("Unknown token {$asset}");
        }

        $token = $this->tokens[$asset];

        if (empty($token['address'])) {
            throw new \RuntimeException("Token contract for {$asset} is not configured");
        }

        $decimals = (int) ($token['decimals'] ?? 18);
        $amountRaw = $this->toTokenUnits((string) $amount, $decimals);
        $data = $this->buildErc20TransferData($to, $amountRaw);

        return $this->postToNode('/estimate-fee', [
            'from' => $from,
            'to' => $token['address'],
            'value' => '0',
            'data' => $data,
        ]);
    }

    protected function buildErc20TransferData(string $to, string $amountRaw): string
    {
        $to = strtolower(trim($to));

        if (!preg_match('/^0x[a-f0-9]{40}$/', $to)) {
            throw new \RuntimeException('Invalid recipient address');
        }

        $methodId = 'a9059cbb'; // transfer(address,uint256)
        $encodedTo = str_pad(substr($to, 2), 64, '0', STR_PAD_LEFT);
        $encodedAmount = str_pad($this->decToHex($amountRaw), 64, '0', STR_PAD_LEFT);

        return '0x' . $methodId . $encodedTo . $encodedAmount;
    }

    protected function toTokenUnits(string $amount, int $decimals): string
    {
        $amount = trim($amount);

        if ($amount === '' || !preg_match('/^\d+(\.\d+)?$/', $amount)) {
            throw new \RuntimeException('Invalid amount format');
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $fraction = substr($fraction, 0, $decimals);
        $fraction = str_pad($fraction, $decimals, '0', STR_PAD_RIGHT);

        $result = ltrim($whole . $fraction, '0');

        return $result === '' ? '0' : $result;
    }

    protected function decToHex(string $dec): string
    {
        $dec = ltrim($dec, '0');

        if ($dec === '' || $dec === '0') {
            return '0';
        }

        if (!function_exists('bccomp')) {
            throw new \RuntimeException('BCMath extension is required for token fee estimation');
        }

        $hex = '';

        while (bccomp($dec, '0', 0) > 0) {
            $remainder = bcmod($dec, '16');
            $hex = dechex((int) $remainder) . $hex;
            $dec = bcdiv($dec, '16', 0);
        }

        return $hex ?: '0';
    }


}

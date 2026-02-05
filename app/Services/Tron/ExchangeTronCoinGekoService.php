<?php

namespace App\Services\Tron;

use App\Http\Enums\BlockChainEnum;
use App\Services\Operations\CurrencyService;
use Illuminate\Support\Facades\Http;


class ExchangeTronCoinGekoService
{
    protected string $url;
    protected string $apiKey;

    public function __construct()
    {
        $this->url = config('services.currencyfreaks.url');
        $this->apiKey = config('services.currencyfreaks.key');
    }

    public function getExchangeRate(string $currency_from, string $currency_to): float
    {
        $from = BlockChainEnum::exchangeCurrency($currency_from);
        $to   = BlockChainEnum::exchangeCurrency($currency_to);

        $rates = $this->getPrice([$from, $to]);

        if (!isset($rates[$from], $rates[$to])) {
            throw new \RuntimeException("Missing rate for {$from} or {$to}");
        }

        $fromUsd = (float) $rates[$from];
        $toUsd   = (float) $rates[$to];

        // from → to через USD
        return $toUsd/$fromUsd;
    }


    protected function getPrice(array $symbols): array
    {
        $base = 'USD';

        $response = Http::timeout(5)
            ->acceptJson()
            ->get($this->url, [
                'apikey'  => $this->apiKey,
                'base'    => strtoupper($base),
                'symbols' => implode(',', array_map('strtoupper', $symbols)),
            ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                'CurrencyFreaks request failed: ' . $response->status()
            );
        }

        return $response->json('rates') ?? [];
    }

    public function getAllPrices(): array
    {
        $base = 'USD';

        $response = Http::timeout(5)
            ->acceptJson()
            ->get($this->url, [
                'apikey' => $this->apiKey,
                'base'   => strtoupper($base),
            ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                'CurrencyFreaks request failed: ' . $response->status()
            );
        }

        return $response->json('rates') ?? [];
    }



}

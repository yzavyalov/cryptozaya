<?php
namespace App\Http\Enums;

enum BlockChainEnum: int
{
//    case ethereum = 1;
    case tron = 2;
//    case binance_smart_chain = 3;
//    case bitcoin = 4;
//    case polygon = 5;

    public function label(): string
    {
        return match ($this) {
//            self::ethereum => 'ethereum',
            self::tron => 'tron',
//            self::binance_smart_chain => 'bsc',
//            self::bitcoin => 'bitcoin',
//            self::polygon => 'polygon',
        };
    }

    // Ассоциативный справочник blockchain => разрешённые валюты
    public static function currencies(): array
    {
        return [
            'ethereum' => ['ETH', 'USDT', 'USDC'],
            'tron' => [1,2,3],
//            'bsc' => ['BNB', 'BUSD', 'USDT'],
//            'bitcoin' => ['BTC'],
//            'polygon' => ['MATIC', 'USDT', 'USDC'],
        ];
    }


    public static function network(): array
    {
        return [
            'tron' => ['TRX','USDT (trc20)','USDC (trc20)'],
            'ethereum' => ['ETH', 'USDT (efc20)', 'USDC (efc20)'],
        ];
    }

    public static function fromCurrency(string $currency): ?self
    {
        foreach (self::network() as $blockchain => $currencies) {
            if (in_array($currency, $currencies, true)) {
                return self::fromName($blockchain);
            }
        }

        return null;

    }



    private static function fromName(string $name): self
    {
        return match ($name) {
            'tron' => self::tron,
//            'ethereum' => self::ethereum,
            default => throw new \InvalidArgumentException("Unknown blockchain: {$name}")
        };
    }

    public static function exchangeCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));

        return match ($currency) {
            'USDT (TRC20)',
            'USDT (ERC20)' => 'USDT',

            'USDC (TRC20)',
            'USDC (ERC20)' => 'USDC',

            'TRX' => 'TRX',
            'ETH' => 'ETH',

            default => $currency, // <-- БЕЗ return
        };
    }

}

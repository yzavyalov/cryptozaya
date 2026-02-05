<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'name' => 'USDT (trc20)',
                'network' => 'tron',
            ],
            [
                'name' => 'USDC (trc20)',
                'network' => 'tron',
            ],
            [
                'name' => 'TRX',
                'network' => 'tron',
            ],
            [
                'name' => 'USDT (erc20)',
                'network' => 'ethereum',
            ],
            [
                'name' => 'USDC (erc20)',
                'network' => 'ethereum',
            ],
            [
                'name' => 'ETH',
                'network' => 'ethereum',
            ],
        ];

        foreach ($data as $currency)
        {
            Currency::firstOrCreate(['name' => $currency['name'], 'network' => $currency['network']]);
        }

    }
}

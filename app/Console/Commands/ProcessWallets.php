<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ProcessWallets extends Command
{
    protected $signature = 'wallets:process';
    protected $description = 'Process TRON wallets and credit user balances';

    public function handle()
    {
        $wallets = Wallet::all();

        foreach ($wallets as $wallet) {
            $transactions = $this->checkTronTransactions($wallet->address);

            foreach ($transactions as $tx) {
                // проверка на уже обработанные транзакции
                if (!WalletTransaction::where('tx_id', $tx['transaction_id'])->exists()) {
                    DB::transaction(function () use ($wallet, $tx) {
                        // создаём запись о транзакции
                        WalletTransaction::create([
                            'tx_id' => $tx['transaction_id'],
                            'user_id' => $wallet->user_id,
                            'amount' => $tx['amount'] / 1_000_000, // TRX в TRX (Sun → TRX)
                        ]);

                        // увеличиваем баланс пользователя
                        $wallet->user->increment('balance', $tx['amount'] / 1_000_000);
                    });
                }
            }
        }
    }

    private function checkTronTransactions($walletAddress)
    {
        $response = Http::get("https://api.trongrid.io/v1/accounts/{$walletAddress}/transactions/trc20", [
            'limit' => 50
        ]);

        return $response->json()['data'] ?? [];
    }
}

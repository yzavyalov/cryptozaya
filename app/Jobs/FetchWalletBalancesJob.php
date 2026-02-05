<?php

namespace App\Jobs;

use App\Services\Operations\BalanceService;
use App\Services\Tron\TronService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchWalletBalancesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $wallet;

    /**
     * Create a new job instance.
     */
    public function __construct(array $wallet)
    {
        $this->wallet = $wallet;
    }

    /**
     * Execute the job.
     */
    public function handle(TronService $tronService)
    {
        if (strtolower($this->wallet['network']) !== 'tron') return;

        $address = $this->wallet['hex'] ?? $this->wallet['number'];

        try {
            Log::info('Fetching balances from Tron node (job)', ['address' => $address]);

            $response = $tronService->getAllBalances($address);

            Log::info('Node response (job)', [
                'wallet' => $this->wallet['number'],
                'response' => $response
            ]);

            $this->wallet->walletBalance()->save([]);


        } catch (\Exception $e) {
            Log::error('Error fetching balances (job)', [
                'wallet' => $this->wallet['number'],
                'error' => $e->getMessage()
            ]);

            // Retry через 5 секунд при 429
            if (str_contains($e->getMessage(), '429')) {
                $this->release(5);
            }
        }

        // Пауза между запросами, чтобы снизить нагрузку
        sleep(1);
    }
}

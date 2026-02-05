<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class CheckWalletsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tron:check-wallets';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check TRON wallets for incoming transactions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        while (true) {
            Artisan::call('wallets:process'); // отдельная команда обработки
            sleep(5);
        }
    }
}

<?php

namespace App\Livewire;

use App\Services\Tron\TronService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class MerchantsWallets extends Component
{
    public $user;
    public $wallets;
    public $merchants;

    public function mount(): void
    {
        $this->refreshWallets();
    }

    protected function loadUserAndMerchants(): void
    {
        $this->user = Auth::user();

        $this->merchants = $this->user->merchants()
            ->with(['mainWallet', 'withdrawWallet'])
            ->get();
    }

    public function loadWallets(): void
    {
        $wallets = [];

        foreach ($this->merchants as $merchant) {
            if ($wallet = $merchant->mainWallet()->first()) {
                $wallets[] = $wallet->toArray();
            }

            if ($wallet = $merchant->withdrawWallet()->first()) {
                $wallets[] = $wallet->toArray();
            }
        }

        $this->wallets = $wallets;
    }

    public function loadWalletsBalancies(): void
    {
            $this->wallets = collect($this->wallets)->map(function ($wallet) {

                if (strtolower($wallet['network']) === 'tron') {
                    try {
                        $address = $wallet['number'] ?? $wallet['hex'];

                        $response = app (TronService::class)->getAllBalances($address);

                        $wallet['balances'] = $response['balances'] ?? [];
                    } catch (\Exception $e) {
                        $wallet['balances'] = ['error' => $e->getMessage()];
                    }
                }

                return $wallet;
            })->toArray();
    }

    public function refreshWallets(): void
    {
        $this->loadUserAndMerchants();
        $this->loadWallets();
        $this->loadWalletsBalancies();
    }

    public function render()
    {
        return view('livewire.merchants-wallets');
    }
}

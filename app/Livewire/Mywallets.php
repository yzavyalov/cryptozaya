<?php

namespace App\Livewire;

use App\Services\Ethereum\EthereumService;
use App\Services\Tron\TronService;
use App\Services\WalletService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class Mywallets extends Component
{
    public $user;
    public $wallets = [];

    /**
     * Инициализация компонента
     */
    public function mount()
    {
        $this->user = Auth::user();
        $this->loadWallets();
        $this->loadWalletsBalancies();
    }

    /**
     * Загружаем только активные кошельки пользователя
     */
    public function loadWallets()
    {
        $this->wallets = $this->user
            ->wallets()
            ->where(function ($q) {
                $q->whereNull('is_hidden')->orWhere('is_hidden', false);
            })
            ->latest()
            ->get()
            ->toArray();
    }

    /**
     * Загружаем балансы всех кошельков
     */
    public function loadWalletsBalancies()
    {
        $this->wallets = collect($this->wallets)->map(function ($wallet) {
            $wallet['balances'] = $wallet['balances'] ?? [];

            try {
                $network = strtolower($wallet['network'] ?? '');
                $address = $wallet['number'] ?? $wallet['hex'] ?? null;

                if (!$address) {
                    return $wallet;
                }

                if ($network === 'tron') {
                    $response = app(TronService::class)->getAllBalances($address);
                    $wallet['balances'] = $response['balances'] ?? [];
                }

                if ($network === 'ethereum') {
                    $response = app(EthereumService::class)->getAllBalances($address);
                    $wallet['balances'] = $response['balances'] ?? [];
                }
            } catch (\Throwable $e) {
                $wallet['balances'] = [
                    'error' => $e->getMessage(),
                ];
            }
Log::info('wallet', [$wallet]);
            return $wallet;
        })->toArray();
    }

    /**
     * Создание нового кошелька
     */
    public function createWallet($blockchain)
    {
        $blockchain = strtolower((string) $blockchain);

        if (!in_array($blockchain, ['tron', 'ethereum'])) {
            return;
        }

        Log::info('createWallet', [$blockchain]);

        try {
            if ($blockchain === 'tron') {
                Log::info('tronservice');
                $response = app(TronService::class)->createWallet();
            } else {
                Log::info('ethereumservice');
                $response = app(EthereumService::class)->createWallet();
                Log::info('ethereumservice-ready', [$response]);
            }

            $newWallet = $response['wallet']
                ?? ($response['data']['wallet'] ?? null)
                ?? (isset($response['data']['address']) ? $response['data'] : null)
                ?? (isset($response['address']) ? $response : null);

            Log::info('newWallet', [$newWallet]);

            if (!is_array($newWallet) || empty($newWallet['address'] ?? $newWallet['number'] ?? null)) {
                throw new \RuntimeException("Invalid {$blockchain} wallet response: wallet address missing");
            }

            $number = $newWallet['number'] ?? $newWallet['address'] ?? null;

            app(WalletService::class)->createWallet(
                $blockchain,
                $number,
                $newWallet['hex'] ?? null,
                $newWallet['publicKey'] ?? null,
                isset($newWallet['encrypted_private_key'])
                    ? json_encode($newWallet['encrypted_private_key'])
                    : null,
                Auth::id()
            );
        } catch (\Throwable $e) {
            Log::error('createWallet error', [
                'blockchain' => $blockchain,
                'message' => $e->getMessage(),
            ]);
        }

        $this->refreshWallets();
    }

    /**
     * Скрываем кошелёк, а не удаляем физически
     */
    public function deleteWallet($walletId)
    {
        $wallet = $this->user
            ->wallets()
            ->where('id', $walletId)
            ->first();

        if (!$wallet) {
            return;
        }

        $wallet->update([
            'is_hidden' => true,
        ]);

        $this->refreshWallets();
    }

    /**
     * Обновление списка кошельков и балансов
     */
    public function refreshWallets()
    {
        $this->user = Auth::user()->fresh();
        $this->loadWallets();
        $this->loadWalletsBalancies();
    }

    public function render()
    {
        return view('livewire.mywallets');
    }
}

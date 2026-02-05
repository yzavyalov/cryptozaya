<?php

namespace App\Livewire;

use App\Services\WalletService;
use App\Services\Tron\TronService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class Mywallets extends Component
{
    public $user;
    public $wallets = [];

    // Сервисы
    protected $tronService;
    protected $walletService;

    public function mount()
    {
        $this->user = Auth::user();
        $this->loadWallets();
        $this->loadWalletsBalancies(); // сразу подгружаем балансы
    }

    /**
     * Загружаем кошельки пользователя
     */
    public function loadWallets()
    {
        $this->wallets = $this->user->wallets()->get()->toArray();
    }

    /**
     * Загружаем балансы всех кошельков
     */
    public function loadWalletsBalancies()
    {
        Log::info('Start loadWalletsBalancies', [
            'wallets_count' => count($this->wallets),
            'time' => now()
        ]);

        $this->wallets = collect($this->wallets)->map(function ($wallet) {
            Log::info('Processing wallet', [
                'number'  => $wallet['number'] ?? null,
                'hex'     => $wallet['hex'] ?? null,
                'network' => $wallet['network']
            ]);

            if (strtolower($wallet['network']) === 'tron') {
                try {
                    $address = $wallet['number'] ?? $wallet['hex'];

                    Log::info('Fetching balances from Tron node', ['address' => $address]);

                    $response = app(TronService::class)->getAllBalances($address);

                    Log::info('Node response', [
                        'wallet' => $wallet['number'],
                        'response' => $response
                    ]);

                    $wallet['balances'] = $response['balances'] ?? [];
                } catch (\Exception $e) {
                    $wallet['balances'] = ['error' => $e->getMessage()];
                    Log::error('Error fetching balances', [
                        'wallet' => $wallet['number'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return $wallet;
        })->toArray();

        Log::info('Finished loadWalletsBalancies', [
            'wallets_count' => count($this->wallets),
            'time' => now()
        ]);
    }

    /**
     * Создание нового кошелька
     */
    public function createWallet($blockchain)
    {
        if (strtolower($blockchain) !== 'tron') {
            Log::info('Blockchain not supported', ['blockchain' => $blockchain]);
            return;
        }

        try {
            $response = app(TronService::class)->createWallet();
            Log::info('response', ['response' => $response]);

            // Поддерживаем несколько форматов: ['wallet'=>...], ['data'=>['wallet'=>...]] или плоский ответ с address
            $newWallet = $response['wallet']
                ?? ($response['data']['wallet'] ?? null)
                ?? (isset($response['address']) ? $response : null);

            if (!is_array($newWallet) || empty($newWallet['address'] ?? $newWallet['number'])) {
                Log::error('Invalid TronService response', ['response' => $response]);
                throw new \RuntimeException('Invalid TronService response: wallet address missing');
            }

            // Нормализуем поля
            $number = $newWallet['number'] ?? $newWallet['address'] ?? null;

            Log::info('wallet response', ['wallet' => $newWallet]);

            $createWallet = app(WalletService::class)->createWallet(
                $blockchain,
                $number,
                $newWallet['hex'] ?? null,
                $newWallet['publicKey'] ?? null,
                isset($newWallet['encrypted_private_key'])
                    ? json_encode($newWallet['encrypted_private_key'])
                    : null,
                Auth::id()
            );

            Log::info('New Tron wallet created', ['wallet' => $createWallet]);
        } catch (\Throwable $e) {
            Log::error('Error creating Tron wallet', ['error' => $e->getMessage()]);
        }

        // Обновляем список кошельков после создания
        $this->loadWallets();
        $this->loadWalletsBalancies();
    }

    public function render()
    {
        return view('livewire.mywallets');
    }
}

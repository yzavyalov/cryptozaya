<?php

namespace App\Livewire;

use App\Services\Tron\TronService;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class WalletBalance extends Component
{
    public string $wallet = '';
    public ?array $balance = null;

    protected function rules(): array
    {
        return [
            'wallet' => [
                'required',
                'string',
                'max:64',
                // запрещаем HTML / теги
                'regex:/^[a-zA-Z0-9]+$/',
            ],
        ];
    }

    protected array $messages = [
        'wallet.required' => 'Wallet address is required.',
        'wallet.regex' => 'Wallet address contains invalid characters.',
    ];

    public function checkBalance()
    {
        $this->validate();

        $this->balance = null;

        try {
            $tron = app(TronService::class);

            $result = $tron->getAllBalances($this->wallet);

            if (!isset($result['balances'])) {
                throw new \Exception('Invalid response from TRON service');
            }

            $this->balance = $result['balances'];

        } catch (\Throwable $e) {
            Log::error('Wallet balance error', [
                'wallet' => $this->wallet,
                'message' => $e->getMessage(),
            ]);

            session()->flash('error', 'Failed to fetch wallet balance.');
        }
    }

    public function render()
    {
        return view('livewire.wallet-balance');
    }
}

<?php

namespace App\Livewire;

use App\Http\Enums\MerchantWalletStatusEnum;
use App\Models\Merchant;
use App\Models\MerchantWallet;
use App\Services\EncodeService;
use App\Services\Ethereum\EthereumService;
use App\Services\Tron\TronService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class MerchantsWallets extends Component
{
    public $user;
    public array $wallets = [];
    public $merchants = [];

    public bool $showCreateWalletForm = false;

    public $selectedMerchantId = null;
    public $selectedNetwork = null;     // tron | ethereum
    public $selectedWalletType = null;  // main | withdraw

    protected $rules = [
        'selectedMerchantId' => 'required|exists:merchants,id',
        'selectedNetwork' => 'required|in:tron,ethereum',
        'selectedWalletType' => 'required|in:main,withdraw',
    ];

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
            $mainWallets = $merchant->mainWallet()->get()->toArray();
            $withdrawWallets = $merchant->withDrawWallet()->get()->toArray();

            $wallets = array_merge($wallets, $mainWallets, $withdrawWallets);
        }

        Log::info('wallets', $wallets);

        $this->wallets = $wallets;
    }

    public function loadWalletBalances(): void
    {
        $this->wallets = collect($this->wallets)
            ->map(function (array $wallet) {
                $network = strtolower((string) data_get($wallet, 'network', ''));
                $address = data_get($wallet, 'number') ?: data_get($wallet, 'hex');

                if (! $address) {
                    $wallet['balances'] = ['error' => 'Wallet address is empty'];
                    return $wallet;
                }

                try {
                    if ($network === 'tron') {
                        $response = app(TronService::class)->getAllBalances($address);
                        $wallet['balances'] = $response['balances'] ?? [];
                    } elseif ($network === 'ethereum') {
                        $response = app(EthereumService::class)->getAllBalances($address);
                        $wallet['balances'] = $wallet['balances'] ?? [];
                    } else {
                        $wallet['balances'] = ['error' => 'Unsupported network'];
                    }
                } catch (\Throwable $e) {
                    $wallet['balances'] = ['error' => $e->getMessage()];
                }

                return $wallet;
            })
            ->values()
            ->toArray();
    }

    public function refreshWallets(): void
    {
        $this->loadUserAndMerchants();

        $this->loadWallets();

        $this->loadWalletBalances();
    }

    public function toggleCreateWalletForm(): void
    {
        $this->showCreateWalletForm = ! $this->showCreateWalletForm;

        if (! $this->showCreateWalletForm) {
            $this->resetCreateWalletForm();
        }
    }

    public function createWallet(): void
    {
        $this->validate();

        $merchant = Merchant::query()
            ->where('id', $this->selectedMerchantId)
            ->firstOrFail();

        $status = $this->selectedWalletType === 'main'
            ? MerchantWalletStatusEnum::MAIN->value
            : MerchantWalletStatusEnum::WITHDRAW->value;

        $existingWallet = MerchantWallet::query()
            ->where('merchant_id', $merchant->id)
            ->where('status', $status)
            ->where('network', $this->selectedNetwork)
            ->first();

        if ($existingWallet) {
            $this->addError('selectedWalletType', 'Wallet with this type already exists for selected merchant.');
            return;
        }

        $walletData = $this->generateWalletData($this->selectedNetwork);

        MerchantWallet::query()->create([
            'merchant_user_id' => null,
            'merchant_transaction_id' => null,
            'status' => $status,
            'merchant_id' => $merchant->id,
            'number' => $walletData['number'],
            'hex' => $walletData['hex'],
            'network' => $this->selectedNetwork,
            'public_key' => $walletData['public_key'],
            'private_key' => EncodeService::encrypte($walletData['private_key']),
        ]);

        session()->flash('success', 'Wallet created successfully.');

        $this->resetCreateWalletForm();
        $this->showCreateWalletForm = false;
        $this->refreshWallets();
    }

    protected function resetCreateWalletForm(): void
    {
        $this->reset([
            'selectedMerchantId',
            'selectedNetwork',
            'selectedWalletType',
        ]);

        $this->resetErrorBag();
        $this->resetValidation();
    }

    protected function generateWalletData(string $network): array
    {
        $service = match ($network) {
            'tron' => app(TronService::class),
            'ethereum' => app(EthereumService::class),
            default => throw new \InvalidArgumentException('Unsupported network'),
        };

        $walletData = $service->createWallet();

        if (isset($walletData['data']) && is_array($walletData['data'])) {
            $walletData = $walletData['data'];
        }

        return [
            'number' => $walletData['address'] ?? null,
            'address' => $walletData['address'] ?? null,
            'public_key' => $walletData['publicKey'] ?? null,
            'hex' => $walletData['hex'] ?? null,
            'private_key' => isset($walletData['encrypted_private_key'])
                ? json_encode($walletData['encrypted_private_key'], JSON_UNESCAPED_UNICODE)
                : null,
        ];
    }

    public function render()
    {
        return view('livewire.merchants-wallets');
    }
}

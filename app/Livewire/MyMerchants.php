<?php

namespace App\Livewire;

use App\Http\Enums\MerchantWalletStatusEnum;
use App\Models\Merchant;
use App\Services\MerchantTokenService;
use App\Services\Operations\MerchantWallet\MerchantWalletService;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class MyMerchants extends Component
{
    public $merchants = [];
    public $generatedTokens = [];

    public array $cburls = [];


    public function mount()
    {
        $this->loadMerchants();
    }

    public function render()
    {
        return view('livewire.my-merchants');
    }

    public function loadMerchants()
    {
        $this->merchants = Auth::user()->merchants()->get();
    }

    public function createToken($id)
    {
        $token = app(MerchantTokenService::class)->createToken();
        $this->generatedTokens[$id] = $token;
    }

    public function storeToken($id)
    {
        $token = $this->generatedTokens[$id] ?? null;
        if (!$token) return;

        $this->dispatch('copy-token', token: $token);

        $merchant = Merchant::findOrFail($id);
        $merchant->token = hash('sha256', $token);
        $merchant->save();

        unset($this->generatedTokens[$id]);

        $this->loadMerchants();
    }

    public function updateToken($id)
    {
        // Сначала генерируем новое
        $this->createToken($id);

        // Потом сохраняем
        $this->storeToken($id);
    }

    public function saveCburl(int $merchantId): void
    {
        $this->validate([
            "cburls.$merchantId" => ['required', 'url', 'max:255'],
        ]);

        $merchant = Merchant::findOrFail($merchantId);
        $merchant->cburl = $this->cburls[$merchantId];
        $merchant->save();

        // чтобы обновилось в таблице
        $this->cburls[$merchantId] = null;
        $this->loadMerchants();
    }


    public function createMainWallet($merchantId)
    {
        $merchant = Merchant::query()->findOrFail($merchantId);

        if ($merchant->mainWallet) {
            return;
        }

        $data['merchant_id'] = $merchantId;

        $merchantWallet = app(MerchantWalletService::class)->create($data);

        $merchantWallet->status = MerchantWalletStatusEnum::MAIN;

        $merchantWallet->save();

        $this->loadMerchants();
    }

    public function createWithdrawWallet($merchantId)
    {
        $merchant = Merchant::query()->findOrFail($merchantId);

        if ($merchant->withDrawWallet()) {
            return;
        }

        $data['merchant_id'] = $merchantId;

        $merchantWallet = app(MerchantWalletService::class)->create($data);

        $merchantWallet->status = MerchantWalletStatusEnum::WITHDRAW;

        $merchantWallet->save();

        $this->loadMerchants();
    }
}

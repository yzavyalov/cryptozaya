<?php

namespace App\Livewire;

use App\Http\Enums\BlockChainEnum;
use App\Models\Currency;
use App\Models\MerchantWallet;
use App\Models\Wallet;
use App\Services\Operations\CurrencyService;
use App\Services\Operations\TransactionService;
use App\Services\Tron\TronService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class SendMoney extends Component
{
    public $user;

    public string $walletType;
    public int|string $walletId;

    public $wallet;
    public $walletBalances = [];

    public $currencies;

    public $blockchain = '';
    public $currency   = '';
    public $amount     = '';
    public $to         = '';

    public function mount(string $walletType, $walletId): void
    {
        $this->user = Auth::user();
        $this->walletType = $walletType;
        $this->walletId = $walletId;

        $this->loadWallet();
        $this->currencies = $this->loadCurrencies();
        $this->loadWalletBalances();
    }

    public function loadWallet(): void
    {
        $this->wallet = null;

        if ($this->walletType === 'wallet') {
            $wallet = Wallet::find($this->walletId);

            if (!$wallet) {
                Log::error('Wallet not found', [
                    'wallet_id' => $this->walletId,
                    'wallet_type' => $this->walletType,
                ]);
                session()->flash('error', 'Wallet not found.');
                return;
            }

            $this->wallet = $wallet;
            return;
        }

        if ($this->walletType === 'merchant_wallet') {
            $merchantWallet = MerchantWallet::find($this->walletId);

            if (!$merchantWallet) {
                Log::error('Merchant wallet not found', [
                    'wallet_id' => $this->walletId,
                    'wallet_type' => $this->walletType,
                ]);
                session()->flash('error', 'Wallet not found.');
                return;
            }

            $merchant = $merchantWallet->merchant;

            if (!$merchant) {
                Log::error('Merchant not found for merchant wallet', [
                    'wallet_id' => $this->walletId,
                    'wallet_type' => $this->walletType,
                ]);
                session()->flash('error', 'Merchant not found.');
                return;
            }

            $hasAccess = $merchant->users->contains('id', $this->user->id);

            if (!$hasAccess) {
                Log::warning('Unauthorized merchant wallet access attempt', [
                    'wallet_id' => $this->walletId,
                    'wallet_type' => $this->walletType,
                    'user_id' => $this->user->id,
                ]);
                session()->flash('error', 'You do not have access to this wallet.');
                return;
            }

            $this->wallet = $merchantWallet;
            return;
        }

        Log::error('Invalid wallet type', [
            'wallet_id' => $this->walletId,
            'wallet_type' => $this->walletType,
            'user_id' => $this->user->id ?? null,
        ]);

        session()->flash('error', 'Invalid wallet type.');
    }

    /**
     * Удаляем только control chars, не ломая UTF-8.
     */
    private function sanitizeNodeResponse($data)
    {
        if (is_array($data)) {
            array_walk_recursive($data, function (&$item) {
                if (is_string($item)) {
                    $item = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $item);
                }
            });
        }

        return $data;
    }

    public function loadWalletBalances(): void
    {
        $this->walletBalances = [];

        if (!$this->wallet) {
            return;
        }

        if (strtolower($this->wallet->network) !== 'tron') {
            return;
        }

        try {
            $address = $this->wallet->hex ?: $this->wallet->number;

            Log::info('Fetching balances from Tron node', ['address' => $address]);

            $response = app(TronService::class)->getAllBalances($address);
            $response = $this->sanitizeNodeResponse($response);

            Log::info('Tron node response', [
                'wallet' => $this->wallet->number,
                'response' => $response,
            ]);

            $this->walletBalances = $response['balances'] ?? [];
        } catch (\Throwable $e) {
            $this->walletBalances = ['error' => $e->getMessage()];

            Log::error('Error fetching balances', [
                'wallet' => $this->wallet->number ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function loadCurrencies()
    {
        return Currency::all();
    }

    public function sendMoney(): void
    {
        session()->forget(['error', 'success']);

        if (!$this->wallet) {
            session()->flash('error', 'Wallet not found.');
            return;
        }

        $blockchainLabels = array_map(fn($enum) => $enum->label(), BlockChainEnum::cases());

        try {
            $this->validate([
                'blockchain' => [
                    'required',
                    'in:' . implode(',', $blockchainLabels),
                ],
                'currency' => [
                    'required',
                    function ($attribute, $value, $fail) {
                        $allowed = BlockChainEnum::currencies()[$this->blockchain] ?? [];

                        if (!in_array($value, $allowed)) {
                            $fail('The selected currency is invalid for the selected blockchain.');
                        }
                    }
                ],
                'amount' => [
                    'required',
                    'numeric',
                    'gt:0',
                    function ($attribute, $value, $fail) {
                        $symbol = BlockChainEnum::exchangeCurrency(
                            CurrencyService::tronDBNameToken($this->currency)
                        );

                        $balance = (float)($this->walletBalances[$symbol] ?? 0);
                        $amount  = (float)$value;

                        if ($amount > $balance) {
                            $fail('The amount exceeds your available balance.');
                        }
                    }
                ],
                'to' => ['required', 'string'],
            ]);
        } catch (ValidationException $e) {
            Log::warning('Validation failed', [
                'errors' => $e->errors(),
            ]);

            session()->flash('error', 'Validation error.');
            return;
        }

        $currencyDbName = CurrencyService::tronDBNameToken($this->currency);
        $amount         = (string)$this->amount;
        $to             = (string)$this->to;

        $wallet = $this->wallet;
        $pk     = $wallet->privateKey ?? $wallet->private_key;

        Log::info('Preparing to send', [
            'wallet_type' => $this->walletType,
            'blockchain' => $this->blockchain,
            'currency' => $currencyDbName,
            'to' => $to,
            'amount' => $amount,
            'wallet_number' => $wallet->number,
        ]);

        try {
            $tron = app(TronService::class);

            $tx = $tron->send(
                CurrencyService::curencyForTronBlockchain($currencyDbName),
                $pk,
                $to,
                $amount
            );

            Log::info('Transaction sent', ['tx' => $tx]);

            $token = $tx['type'] ?? $currencyDbName;

            app(TransactionService::class)->create(
                $this->blockchain,
                $wallet->number,
                $to,
                $amount,
                CurrencyService::tronToken($token)
            );

            Log::info('Transaction recorded in DB', [
                'wallet_type' => $this->walletType,
                'blockchain' => $this->blockchain,
                'wallet_number' => $wallet->number,
                'to' => $to,
                'amount' => $amount,
                'token' => $token,
            ]);

            session()->flash('success', 'Transaction successfully sent.');

            $this->reset(['amount', 'to', 'currency']);

            $this->loadWalletBalances();

        } catch (\Throwable $e) {
            Log::error('Transaction error', [
                'wallet_type' => $this->walletType,
                'message' => $e->getMessage(),
                'currency' => $currencyDbName,
                'to' => $to,
                'amount' => $amount,
            ]);

            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.send-money');
    }
}

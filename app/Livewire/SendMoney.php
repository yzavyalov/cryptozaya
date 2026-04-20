<?php

namespace App\Livewire;

use App\Http\Enums\BlockChainEnum;
use App\Models\Currency;
use App\Models\MerchantWallet;
use App\Models\Wallet;
use App\Services\Ethereum\EthereumService;
use App\Services\Operations\CurrencyService;
use App\Services\Operations\TransactionService;
use App\Services\Tron\TronService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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

        if ($this->wallet) {
            $this->blockchain = strtolower((string) $this->wallet->network);
        }

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

    public function updatedBlockchain(): void
    {
        $this->currency = '';
        $this->loadWalletBalances();
    }

    public function loadWalletBalances(): void
    {
        $this->walletBalances = [];

        if (!$this->wallet) {
            return;
        }

        $network = strtolower((string) ($this->wallet->network ?? $this->blockchain));

        try {
            if ($network === 'tron') {
                $address = $this->wallet->hex ?: $this->wallet->number;

                Log::info('Fetching balances from Tron node', ['address' => $address]);

                $response = app(TronService::class)->getAllBalances($address);
                $response = $this->sanitizeNodeResponse($response);

                Log::info('Tron node response', [
                    'wallet' => $this->wallet->number,
                    'response' => $response,
                ]);

                $this->walletBalances = $response['balances'] ?? [];
                return;
            }

            if ($network === 'ethereum') {
                $address = $this->wallet->number;

                Log::info('Fetching balances from Ethereum node', ['address' => $address]);

                $response = app(EthereumService::class)->getAllBalances($address);
                $response = $this->sanitizeNodeResponse($response);

                Log::info('Ethereum node response', [
                    'wallet' => $this->wallet->number,
                    'response' => $response,
                ]);

                $this->walletBalances = $response['balances'] ?? [];
                return;
            }

            $this->walletBalances = [];
        } catch (\Throwable $e) {
            $this->walletBalances = ['error' => $e->getMessage()];

            Log::error('Error fetching balances', [
                'wallet' => $this->wallet->number ?? null,
                'network' => $network,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function loadCurrencies()
    {
        return Currency::all();
    }

    protected function resolveCurrencyForSend(string $blockchain, string $currency): string
    {
        $blockchain = strtolower($blockchain);
//        $currency = strtoupper($currency);


        if ($blockchain === 'tron') {
            return CurrencyService::curencyForTronBlockchain(
                $currency
            );
        }

        if ($blockchain === 'ethereum') {
            return CurrencyService::curencyForTronBlockchain(
                $currency
            );
        }

        throw new \RuntimeException("Unsupported blockchain {$blockchain}");
    }

    protected function resolveCurrencyForTransaction(string $blockchain, string $currency): string
    {
        $blockchain = strtolower($blockchain);
        $currency = strtoupper($currency);

        if ($blockchain === 'tron') {
            return CurrencyService::tronToken($currency);
        }

        if ($blockchain === 'ethereum') {
            return CurrencyService::tronToken($currency);
        }

        return $currency;
    }

    protected function resolveBalanceSymbol(string $blockchain, string $currency): string
    {
        $blockchain = strtolower($blockchain);
        $currency = strtoupper($currency);

        if ($blockchain === 'tron') {
            $dbName = CurrencyService::tronDBNameToken($currency);
            return BlockChainEnum::exchangeCurrency($dbName);
        }

        if ($blockchain === 'ethereum') {
            $dbName = CurrencyService::tronDBNameToken($currency);
            return BlockChainEnum::exchangeCurrency($dbName);
        }

        return $currency;
    }

    public function sendMoney(): void
    {
        session()->forget(['error', 'success']);
        $this->resetErrorBag();
        $this->resetValidation();

        if (!$this->wallet) {
            session()->flash('error', 'Wallet not found.');
            return;
        }

        $walletNetwork = strtolower((string) $this->wallet->network);
        $selectedBlockchain = strtolower((string) $this->blockchain);

        if ($selectedBlockchain !== $walletNetwork) {
            $this->addError('blockchain', 'Selected blockchain does not match wallet network.');
            return;
        }

        $blockchainLabels = array_map(fn($enum) => $enum->label(), BlockChainEnum::cases());

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
                function ($attribute, $value, $fail) use ($selectedBlockchain) {
                    $symbol = $this->resolveBalanceSymbol($selectedBlockchain, $this->currency);

                    $balance = (float)($this->walletBalances[$symbol] ?? 0);
                    $amount  = (float) str_replace(',', '.', (string) $value);

                    if ($amount > $balance) {
                        $fail('The amount exceeds your available balance.');
                    }
                }
            ],
            'to' => ['required', 'string'],
        ]);

        $currencyForSend = CurrencyService::tronDBNameToken($this->currency) ?? null;
        $amount          = (string) str_replace(',', '.', (string) $this->amount);
        $to              = (string) $this->to;

        $wallet = $this->wallet;
        $pk     = $wallet->privateKey ?? $wallet->private_key ?? null;

        if (!$pk) {
            session()->flash('error', 'Private key not found.');
            return;
        }

        Log::info('Preparing to send', [
            'wallet_type'    => $this->walletType,
            'blockchain'     => $selectedBlockchain,
            'currency'       => $currencyForSend,
            'to'             => $to,
            'amount'         => $amount,
            'wallet_number'  => $wallet->number,
            'has_private_key'=> !empty($pk),
        ]);

        try {
            if ($selectedBlockchain === 'tron') {
                $service = app(TronService::class);
                $tx = $service->send($currencyForSend, $pk, $to, $amount);
            } elseif ($selectedBlockchain === 'ethereum') {
                $service = app(EthereumService::class);
                $tx = $service->send($currencyForSend, $pk, $to, $amount);
            } else {
                throw new \RuntimeException("Unsupported blockchain {$selectedBlockchain}");
            }

            Log::info('Transaction sent', [
                'blockchain' => $selectedBlockchain,
                'response' => $tx,
            ]);

            $token = strtoupper((string) ($tx['type'] ?? $this->currency));

            app(TransactionService::class)->create(
                $selectedBlockchain,
                $wallet->number,
                $to,
                $amount,
                $token,
            );

            Log::info('Transaction recorded in DB', [
                'wallet_type'   => $this->walletType,
                'blockchain'    => $selectedBlockchain,
                'wallet_number' => $wallet->number,
                'to'            => $to,
                'amount'        => $amount,
                'token'         => $token,
            ]);

            session()->flash('success', 'Transaction successfully sent.');

            $this->reset(['amount', 'to', 'currency']);
            $this->blockchain = $walletNetwork;

            $this->loadWalletBalances();
        } catch (\Throwable $e) {
            $networkResponse = method_exists($e, 'getResponse')
                ? $e->getResponse()
                : null;

            Log::error('Transaction error', [
                'wallet_type'      => $this->walletType,
                'message'          => $e->getMessage(),
                'exception'        => get_class($e),
                'currency'         => $currencyForSend,
                'to'               => $to,
                'amount'           => $amount,
                'blockchain'       => $selectedBlockchain,
                'network_response' => $networkResponse,
            ]);

            $message = $this->extractReadableNetworkError($e);

            session()->flash('error', $message);
        }
    }


    protected function extractReadableNetworkError(\Throwable $e): string
    {
        $message = $e->getMessage();

        if (empty($message)) {
            return 'Unknown network error.';
        }

        if (str_contains($message, 'insufficient funds')) {
            return 'Insufficient funds for transfer and network fee.';
        }

        if (str_contains($message, 'encrypted_private_key is required')) {
            return 'Encrypted private key is required by the blockchain node.';
        }

        if (str_contains($message, 'service unavailable')) {
            return $message;
        }

        return $message;
    }

    public function getFilteredCurrenciesProperty()
    {
        if (!$this->blockchain) {
            return collect();
        }

        return $this->currencies->filter(function ($currency) {
            return strtolower((string) $currency->network) === strtolower((string) $this->blockchain);
        });
    }


    public function render()
    {
        return view('livewire.send-money');
    }
}

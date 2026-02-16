<?php

namespace App\Livewire;

use App\Http\Enums\BlockChainEnum;
use App\Models\Currency;
use App\Models\Wallet;
use App\Services\EncodeService;
use App\Services\Operations\BalanceService;
use App\Services\Operations\CurrencyService;
use App\Services\Operations\TransactionService;
use App\Services\Tron\TronService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class SendMoney extends Component
{
    public $user;
    public $yourBalances;
    public $walletId;

    public $wallet;
    public $walletBalances = [];

    public $currencies;

    public $blockchain = '';

    public $currency = '';

    public $amount = '';

    public $to;

    public function mount($walletId)
    {
        $this->user = Auth::user();

        $this->walletId = $walletId;

        $this->loadWallet();
        $this->loadWalletsBalancies();

        $this->currencies = $this->loadCurrencies();
    }

    public function loadWallet()
    {
        $this->wallet = Wallet::find($this->walletId);
    }


    private function normalizeUtf8($data)
    {
        array_walk_recursive($data, function (&$item) {
            if (is_string($item)) {
                // Принудительная конвертация в UTF-8
                $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8');

                // Удалить бинарный мусор
                $item = preg_replace('/[^\x20-\x7E]/', '', $item);
            }
        });

        return $data;
    }

    public function loadWalletsBalancies()
    {
        $wallet = $this->wallet;

        $this->walletBalances = [];

        if (!$wallet) {
            Log::error('Wallet not found', [
                'wallet_id' => $this->walletId
            ]);
            return;
        }

        if (strtolower($wallet->network) === 'tron') {
            try {
                $address = $wallet->hex ?? $wallet->number;

                Log::info('Fetching balances from Tron node', ['address' => $address]);

                $response = app(TronService::class)->getAllBalances($address);

                // 🔥 Добавляем фиксацию UTF-8
                $response = $this->normalizeUtf8($response);

                Log::info('Tron node full response', $response);
                Log::info('Node response', [
                    'wallet' => $wallet->number,
                    'response' => $response
                ]);

                $this->walletBalances = $response['balances'] ?? [];

            } catch (\Exception $e) {
                $this->walletBalances = [
                    'error' => $e->getMessage()
                ];

                Log::error('Error fetching balances', [
                    'wallet' => $wallet->number,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }



    public function loadCurrencies()
    {
        return Currency::all();
    }


    public function sendMoney()
    {
        Log::info('sendMoney started', [
            'user_id' => $this->user->id ?? null,
            'wallet_id' => $this->wallet->id ?? null
        ]);

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
                            $fail("The selected currency '{$value}' is invalid for the selected blockchain '{$this->blockchain}'.");
                        }
                    }
                ],
                'amount' => [
                    'required',
                    'numeric',
                    'gt:0',
                    function ($attribute, $value, $fail) {
                        $symbol = BlockChainEnum::exchangeCurrency(CurrencyService::tronDBNameToken($this->currency));
                        // получаем баланс пользователя для выбранной валюты
                        $balance = (float) ($this->walletBalances[$symbol] ?? 0);

                        $amount = (float) $value;

                        if ($amount > $balance) {
                            $fail("The amount exceeds your available balance.");
                        }
                    }
                ],
                'to' => ['required', 'string'],
            ]);

            Log::info('Validation passed', [
                'blockchain' => $this->blockchain,
                'currency' => $this->currency,
                'amount' => $this->amount,
                'to' => $this->to
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation failed', [
                'errors' => $e->errors(),
                'input' => request()->all()
            ]);
            session()->flash('error', 'Validation error: ' . json_encode($e->errors()));
            return;
        }

        $currency = CurrencyService::tronDBNameToken($this->currency); // 'USDT (trc20)','USDC (trc20)', 'TRX'
        $amount   = $this->amount;
        $to       = $this->to;
        $wallet   = $this->wallet;               // твой объект Wallet
        $pk       = $wallet->privateKey;

        Log::info('Preparing to send', [
            'currency' => $currency,
            'to' => $to,
            'amount' => $amount,
            'wallet_number' => $wallet->number,
            'privateKey_snippet' => substr($pk, 0, 6) . '***' // не логируем полностью!
        ]);

        $tron = app(\App\Services\Tron\TronService::class);

        try {
            $tx = $tron->send(CurrencyService::curencyForTronBlockchain($currency), $pk,$to,$amount);

            Log::info('Transaction sent', ['tx' => $tx]);

            // Имя токена (USDT, USDC, ...)
            $token = $tx['type'] ?? $currency;

            app(TransactionService::class)->create(
                $this->blockchain,
                $wallet->number,
                $to,
                $amount,
                CurrencyService::tronToken($token)
            );

            Log::info('Transaction recorded in DB', [
                'blockchain' => $this->blockchain,
                'wallet_number' => $wallet->number,
                'to' => $to,
                'amount' => $amount,
                'token' => $token
            ]);

//            app(BalanceService::class)->reduction(
//                $this->user->id,
//                $amount,
//                CurrencyService::tronToken($token)
//            );

//            Log::info('User balance reduced', [
//                'user_id' => $this->user->id,
//                'amount' => $amount,
//                'token' => $token
//            ]);

        } catch (\Exception $e) {
            Log::error('Transaction error', [
                'message' => $e->getMessage(),
                'currency' => $currency,
                'to' => $to,
                'amount' => $amount
            ]);

            session()->flash('error', $e->getMessage());
        }

        Log::info('sendMoney finished');
    }


    public function render()
    {
        return view('livewire.send-money');
    }
}

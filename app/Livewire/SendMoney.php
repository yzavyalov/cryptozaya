<?php

namespace App\Livewire;

use App\Http\Enums\BlockChainEnum;
use App\Models\Currency;
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
    public $walletId;

    public $wallet;
    public $walletBalances = [];

    public $currencies;

    public $blockchain = '';
    public $currency   = '';
    public $amount     = '';
    public $to         = '';

    public function mount($walletId)
    {
        $this->user = Auth::user();
        $this->walletId = $walletId;

        $this->loadWallet();
        $this->currencies = $this->loadCurrencies();

        $this->loadWalletBalances();
    }

    public function loadWallet(): void
    {
        $this->wallet = Wallet::find($this->walletId);

        if (!$this->wallet) {
            Log::error('Wallet not found', ['wallet_id' => $this->walletId]);
            session()->flash('error', 'Wallet not found.');
        }
    }

    /**
     * ВАЖНО:
     * Твой прошлый normalizeUtf8 вырезал ВСЁ кроме ASCII (регекс [^\x20-\x7E]),
     * из-за этого мог ломать ответы/токены/сообщения.
     * Здесь мы удаляем только управляющие (control) символы, а не весь UTF-8.
     */
    private function sanitizeNodeResponse($data)
    {
        if (is_array($data)) {
            array_walk_recursive($data, function (&$item) {
                if (is_string($item)) {
                    // убираем только control chars, оставляя UTF-8
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
            // можно расширить под другие сети
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
                'wallet' => $this->wallet->number,
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
        // чистим старые flash чтобы не "залипали" в UI
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
                        // $value тут — currency id из селекта
                        $allowed = BlockChainEnum::currencies()[$this->blockchain] ?? [];

                        if (!in_array($value, $allowed)) {
                            $fail("The selected currency is invalid for the selected blockchain.");
                        }
                    }
                ],
                'amount' => [
                    'required',
                    'numeric',
                    'gt:0',
                    function ($attribute, $value, $fail) {
                        // Проверка баланса по уже загруженным walletBalances
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
            // ошибки полей Livewire сам покажет через @error
            return;
        }

        $currencyDbName = CurrencyService::tronDBNameToken($this->currency); // 'USDT (trc20)','USDC (trc20)', 'TRX'
        $amount         = (string)$this->amount;
        $to             = (string)$this->to;

        $wallet = $this->wallet;
        $pk     = $wallet->privateKey;

        Log::info('Preparing to send', [
            'blockchain' => $this->blockchain,
            'currency' => $currencyDbName,
            'to' => $to,
            'amount' => $amount,
            'wallet_number' => $wallet->number,
        ]);

        try {
            $tron = app(TronService::class);

            // Отправка в Tron
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
                'blockchain' => $this->blockchain,
                'wallet_number' => $wallet->number,
                'to' => $to,
                'amount' => $amount,
                'token' => $token,
            ]);

            // ✅ SUCCESS MESSAGE
            session()->flash('success', 'Transaction successfully sent.');

            // Очистим форму (чтобы пользователь не отправил повторно случайно)
            $this->reset(['amount', 'to', 'currency']);

            // Обновим балансы после транзакции
            $this->loadWalletBalances();

        } catch (\Throwable $e) {
            Log::error('Transaction error', [
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

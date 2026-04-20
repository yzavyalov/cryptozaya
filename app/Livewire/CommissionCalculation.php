<?php

namespace App\Livewire;

use App\Models\Currency;
use App\Services\Ethereum\EthereumService;
use App\Services\Operations\CurrencyService;
use App\Services\Tron\TronService;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class CommissionCalculation extends Component
{
    public $currencies;

    public $blockchain;
    public $currency;
    public $amount;
    public $from;
    public $to;

    public $networkFee;
    public $serviceFee;
    public $totalAmount;
    public $feeCurrency = null;

    public bool $loading = false;

    public function mount()
    {
        $this->currencies = Currency::all();
    }

    public function recalculate()
    {
        if ($this->loading) {
            return;
        }

        $this->resetCalculation();

        if (
            !$this->blockchain ||
            !$this->currency ||
            !$this->amount ||
            $this->amount <= 0 ||
            !$this->from ||
            !$this->to
        ) {
            session()->flash('error', 'Please fill in all required fields.');
            return;
        }

        $this->loading = true;

        try {
            switch (strtolower((string) $this->blockchain)) {
                case 'tron':
                    $this->calculateTronFee();
                    break;

                case 'ethereum':
                case 'eth':
                    $this->calculateEthereumFee();
                    break;

                default:
                    session()->flash('error', 'Unsupported blockchain.');
                    $this->resetCalculation();
                    return;
            }
        } catch (\Throwable $e) {
            Log::error('FEE CALCULATION ERROR', [
                'blockchain' => $this->blockchain,
                'currency' => CurrencyService::tronDBNameToken($this->currency),
                'amount' => $this->amount,
                'from' => $this->from,
                'to' => $this->to,
                'message' => $e->getMessage(),
            ]);

            session()->flash('error', 'Failed to calculate commission.');
            $this->resetCalculation();
        } finally {
            $this->loading = false;
        }
    }

    public function updatedBlockchain()
    {
        $this->currency = null;
        $this->resetCalculation();
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

    protected function calculateTronFee(): void
    {
        Log::info('START TRON FEE CALCULATION', [
            'currency' => $this->currency,
            'from' => $this->from,
            'to' => $this->to,
            'amount' => $this->amount,
        ]);

        $tron = app(TronService::class);

        $asset = $this->resolveEthereumAsset();

        $data = $tron->estimateTRC20Fee(
            $asset,
            $this->from,
            $this->to,
            $this->amount
        );

        $this->networkFee = (float) ($data['network_fee'] ?? 0);
        $this->serviceFee = (float) ($data['service_fee'] ?? 0);
        $this->feeCurrency = $data['fee_currency'] ?? 'TRX';
        $this->totalAmount = $this->networkFee + $this->serviceFee;

        Log::info('TRON FEE CALCULATED', $data);
    }

    protected function calculateEthereumFee(): void
    {
        Log::info('START ETHEREUM FEE CALCULATION', [
            'currency' => $this->currency,
            'from' => $this->from,
            'to' => $this->to,
            'amount' => $this->amount,
        ]);

        $ethereum = app(EthereumService::class);

        $asset = $this->resolveEthereumAsset();

        $response = $ethereum->estimateFee(
            $asset,
            $this->from,
            $this->to,
            $this->amount
        );

        $data = $response['data'] ?? $response;

        $estimatedFeeEth = (float) ($data['estimatedFeeEth'] ?? 0);

        $this->networkFee = $estimatedFeeEth;
        $this->serviceFee = 0;
        $this->feeCurrency = 'ETH';
        $this->totalAmount = $this->networkFee;

        Log::info('ETHEREUM FEE CALCULATED', [
            'response' => $response,
            'parsed_fee_eth' => $estimatedFeeEth,
        ]);
    }

    protected function resolveEthereumAsset(): string
    {
        $currency = (string) CurrencyService::tronDBNameToken($this->currency);

        return match ($currency) {
            'ETH' => 'ETH',
            'TRX' => 'TRX',
            'USDT (trc20)' => 'USDT',
            'USDT (erc20)' => 'USDT',
            'USDC (trc20)' => 'USDC',
            'USDC (erc20)' => 'USDC',
            default => $currency,
        };
    }

    protected function resetCalculation(): void
    {
        $this->networkFee = null;
        $this->serviceFee = null;
        $this->totalAmount = null;
        $this->feeCurrency = null;
    }

    public function render()
    {
        return view('livewire.commission-calculation');
    }
}

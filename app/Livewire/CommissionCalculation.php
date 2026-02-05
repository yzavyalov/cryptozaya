<?php

namespace App\Livewire;

use App\Models\Currency;
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
    public $feeCurrency = 'TRX';

    public bool $loading = false;

    public function mount()
    {
        $this->currencies = Currency::all();
    }

    /**
     * Единственная точка входа для расчёта
     */
    public function recalculate()
    {
        if ($this->loading) {
            return;
        }

        $this->resetCalculation();

        // Базовая валидация
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

        if ($this->blockchain !== 'tron') {
            session()->flash('error', 'Only TRON blockchain is supported.');
            return;
        }

        $this->loading = true;

        try {
            Log::info('start TRON FEE CALCULATION');
            $tron = app(TronService::class);

            $data = $tron->estimateTRC20Fee(
                CurrencyService::tronDBNameToken($this->currency),
                $this->from,
                $this->to,
                $this->amount
            );
Log::info('estimateTRC20Fee', $data);
            $this->networkFee  = (float) ($data['network_fee'] ?? 0);
            $this->serviceFee  = (float) ($data['service_fee'] ?? 0);
            $this->feeCurrency = $data['fee_currency'] ?? 'TRX';
            $this->totalAmount = $this->networkFee + $this->serviceFee;

            Log::info('TRON FEE CALCULATED', $data);

        } catch (\Throwable $e) {
            Log::error('TRON FEE ERROR', [
                'message' => $e->getMessage()
            ]);

            session()->flash('error', 'Failed to calculate commission.');
            $this->resetCalculation();
        } finally {
            $this->loading = false;
        }
    }

    protected function resetCalculation(): void
    {
        $this->networkFee  = null;
        $this->serviceFee  = null;
        $this->totalAmount = null;
        $this->feeCurrency = 'TRX';
    }

    public function render()
    {
        return view('livewire.commission-сalculation');
    }
}

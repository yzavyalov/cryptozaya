<?php

namespace App\Livewire;

use App\Services\Tron\ExchangeTronCoinGekoService;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class CabinetMainPage extends Component
{
    public $rates = [];

    protected $listeners = ['refreshRates' => 'loadRates'];

    public function mount()
    {
        $this->loadRates();
    }

    public function loadRates()
    {
        $this->rates = app(ExchangeTronCoinGekoService::class)->getAllPrices();
    }

    public function render()
    {
        return view('livewire.cabinet-main-page');
    }
}

<?php

namespace App\Livewire;

use App\Http\Requests\CurrencyRequest;
use App\Models\Currency;
use Livewire\Component;

class Currencies extends Component
{
    public $name = '';

    public $currencies = [];

    public function mount()
    {
        $this->currencies = Currency::all();
    }

    public function create()
    {
        Currency::create(
            $this->only(['name'])
        );

        session()->flash('status','Currency was created');

        $this->loadCurrencies(); // ✅ обновление таблицы сразу
    }

    public function loadCurrencies()
    {
        $this->currencies = Currency::all();
    }

    public function render()
    {
        return view('livewire.currencies');
    }
}

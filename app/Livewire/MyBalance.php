<?php
namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class MyBalance extends Component
{
    public $user;
    public $balances = [];
    public $merchantBalances = [];

    public function mount()
    {
        $this->user = Auth::user();
        $this->balances = $this->user->balances()->get();
        $this->merchantBalances = $this->user->merchantBalances()->get();
    }

    public function loadBalance()
    {
        $this->balances = $this->user->balances()->get();
    }

    public function loadMerchantBalances()
    {
        $this->merchantBalances = $this->user->merchantBalances()->get();
    }

    public function render()
    {
        return view('livewire.my-balance');
    }
}

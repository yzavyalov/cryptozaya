<?php

namespace App\Livewire;

use App\Http\Enums\MerchantStatusEnum;
use App\Models\Merchant;
use Livewire\Component;

class AllMerchants extends Component
{
    public $merchants;

    public function mount()
    {
        $this->loadMerchants();
    }

    public function loadMerchants()
    {
        $this->merchants = Merchant::all();
    }


    public function blockMerchant($id)
    {
        $merchant = Merchant::findOrFail($id);

        if ($merchant->status !== MerchantStatusEnum::BLOCKED->value && $merchant->status !== MerchantStatusEnum::DELETED->value)
            $merchant->status = MerchantStatusEnum::BLOCKED->value;
        else
            $merchant->status = MerchantStatusEnum::PAID->value;

        $merchant->save();

        $this->loadMerchants();
    }

    public function paidStatus($id)
    {
        $merchant = Merchant::findOrFail($id);

        if (in_array($merchant->status, [
            MerchantStatusEnum::DELETED->value,
            MerchantStatusEnum::BLOCKED->value
        ])) {
            return; // или throw или flash error
        }

        // Переключение только Paid / Unpaid
        $merchant->status =
            $merchant->status === MerchantStatusEnum::PAID->value
                ? MerchantStatusEnum::UNPAID->value
                : MerchantStatusEnum::PAID->value;

        $merchant->save();

        $this->loadMerchants();
    }

    public function render()
    {
        return view('livewire.all-merchants');
    }
}

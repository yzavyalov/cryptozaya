<?php

namespace App\Livewire;

use App\Services\UserService;
use Livewire\Component;

class NewUserForm extends Component
{

    public function render()
    {
        return view('livewire.new-user-form');
    }
}

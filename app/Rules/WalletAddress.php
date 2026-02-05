<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class WalletAddress implements Rule
{
    public function passes($attribute, $value)
    {
        $ethRegex = '/^0x[a-fA-F0-9]{40}$/';
        $tronRegex = '/^T[1-9A-HJ-NP-Za-km-z]{33}$/';

        return preg_match($ethRegex, $value) || preg_match($tronRegex, $value);
    }

    public function message()
    {
        return 'The :attribute must be a valid Ethereum or TRON address.';
    }
}


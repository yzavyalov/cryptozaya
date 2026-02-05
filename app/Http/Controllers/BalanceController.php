<?php

namespace App\Http\Controllers;

use App\Models\MerchantBalance;
use App\Models\UserBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BalanceController extends Controller
{
    public function allUsersBalance()
    {
        $balances = UserBalance::all();

        return view('cabinet.operations.all-users-balance', compact('balances'));
    }

    public function allMerchantsBalance()
    {
        $balances = MerchantBalance::all();

        return view('cabinet.operations.all-merchants-balance', compact('balances'));
    }


    public function myBalance()
    {
        return view('cabinet.operations.my-balance');
    }
}

<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\CurrencyRequest;
use App\Models\Wallet;
use Illuminate\Http\Request;

class SendMoneyController extends Controller
{
    public function form($walletId)
    {
        return view('cabinet.operations.send-money',compact('walletId'));
    }


    public function sendMoney(CurrencyRequest $request)
    {
        $currency = $request->validated()['currency'];

        dd($currency);
    }


}

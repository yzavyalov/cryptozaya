<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Wallet;
use App\Services\Tron\TronDepositMonitorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OperationController extends Controller
{
    public function __construct(TronDepositMonitorService $depositMonitorService)
    {
        $this->depositMonitorService = $depositMonitorService;
    }

    public function index()
    {
        return view('cabinet.operations.index');
    }

    public function topupForm()
    {
        return view('cabinet.operations.topup-form');
    }

    public function currencies()
    {
        return view('cabinet.currencies');
    }


    public function myWallets()
    {
        return view('cabinet.operations.mywallets');
    }

    public function commission()
    {
        return view('cabinet.operations.tron-commission');
    }

    public function walletBalance()
    {
        return view('cabinet.operations.tron-balance');
    }

    public function checkTransactions()
    {
        $user = Auth::user();

        $wallets = $user->wallets()->get();

        foreach ($wallets as $wallet)
        {
            $check = $this->depositMonitorService->scanWallet($wallet);

            dd($check);

        }
    }
}

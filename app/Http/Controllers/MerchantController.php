<?php

namespace App\Http\Controllers;

use App\Http\Enums\MerchantStatusEnum;
use App\Http\Enums\MerchantTransactionStatusEnum;
use App\Http\Enums\MerchantTypeTransactionEnum;
use App\Http\Requests\MerchantCreateRequest;
use App\Models\Merchant;
use App\Models\Merchanttransaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MerchantController extends Controller
{
    public function createMerchantForm()
    {
        return view('cabinet.new-merchant-form');
    }

    public function createMerchant(MerchantCreateRequest $request)
    {
        $data = $request->validated();

        $merchant = Merchant::query()->where('name',$data['name'])->first();

        if (!$merchant)
        {
            $user = Auth::user();

            $user->merchants()->create([
                'name' => $data['name'],
                'status' => MerchantStatusEnum::UNPAID,
                'cburl' => $data['cburl'] ?? null,
            ]);

            if (!$user->hasRole('admin'))
            {
                $user->removeRole('user');

                $user->assignRole('merchant');
            }
        }

        return redirect()->route('my-merchants');
    }

    public function myMerchants()
    {
        return view('cabinet.my-merchants');
    }

    public function allMerchants()
    {
        return view('cabinet.all-merchants');
    }

    public function transcationHistory()
    {
        $transactions = Merchanttransaction::whereHas('merchant', function ($q) {
            $q->whereIn(
                'merchants.id',
                Auth::user()->merchants()->pluck('merchants.id')
            );
        })->get();

        return view('cabinet.operations.all-merchant-transactions', compact('transactions'));
    }

    public function allDeposits()
    {
        $transactions = Merchanttransaction::whereHas('merchant', function ($q) {
            $q->whereIn(
                'merchants.id',
                Auth::user()->merchants()->pluck('merchants.id')
            );
        })
            ->where('type_transactions', MerchantTypeTransactionEnum::deposit)
            ->whereIn('status', [
                MerchantTransactionStatusEnum::successful,
                MerchantTransactionStatusEnum::withoutInitialization, // ← второй статус
            ])
            ->get();

        return view('cabinet.operations.all-merchant-deposits', compact('transactions'));
    }
}

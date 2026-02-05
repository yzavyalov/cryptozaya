<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Enums\MerchantTransactionStatusEnum;
use App\Http\Enums\MerchantTypeTransactionEnum;
use App\Http\Requests\MainWalletRequest;
use App\Models\MerchantWallet;
use App\Services\Operations\CurrencyService;
use App\Services\Operations\MerchantWallet\MerchantWalletService;
use App\Services\Tron\TronHelper;
use App\Services\Tron\TronService;
use Illuminate\Http\Request;

class WithdrawDepositsController extends Controller
{
    public function __construct(TronService $tronService)
    {
        $this->tronService = $tronService;
    }
    public function withdrawDeposits(MainWalletRequest $request)
    {
        $validated = $request->validated();

        $walletMerchant = MerchantWallet::query()->findOrFail($validated['wallet_id']);

        $merchant = $walletMerchant->merchant;

        $merchantMainWallet = $merchant->mainWallet();

        $deposits = $merchant->transactions()->where('type_transactions',MerchantTypeTransactionEnum::deposit)
            ->whereIn('status', [MerchantTransactionStatusEnum::successful,
                                 MerchantTransactionStatusEnum::withoutInitialization])->get();

        //считаем комиссию
        $allCommission['total_fee'] = 0;

        $transactions = [];

        foreach ($deposits as $deposit)
        {
            $token = CurrencyService::curencyForTronBlockchain(CurrencyService::tronDBNameToken($deposit->currency_id));

            $commission =$this->tronService->estimateTRC20Fee($token,$deposit->wallet_to,$merchantMainWallet->number,$deposit->sum);

            $transactions[] = ['address' => $deposit->wallet_to,
                                        'amount' => $deposit->sum,
                                        'token' => $token,
                                        'commission' => $commission['total_fee']];

            $allCommission['total_fee'] += $commission['total_fee'];

            $allCommission['fee_currency'] = $commission['fee_currency'];
        }

        $balanceMerchantMainWallet = $this->tronService->getAllBalances($merchantMainWallet->number);

        if ($balanceMerchantMainWallet['balances'][$allCommission['fee_currency']] < $allCommission['total_fee'])
        {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors([
                    'balance' => 'You need to top up your balance for the commission on '.$allCommission['total_fee'].$allCommission['fee_currency'],
                ]);
        }
        else
        {
            foreach($transactions as $transaction)
            {
                $realBalance = $this->tronService->getAllBalances($transaction['address']);
                //делаем транзакцию в трх
                if ($realBalance['balances'][$transaction['token']] >= $transaction['amount'])
                {
                    //делаем транзакцию в трх
                    $trx = $this->tronService->send('TRX',$merchantMainWallet->private_key, $transaction['address'], $transaction['commission']);

                    $resultTRXtransaction = $this->tronService->waitForTrxConfirmation($trx['txid']);
                    // потом транзакцию выводим токен на кошелек мерчанта
                    $activeWallet = $this->tronService->isAccountActivated(MerchantWalletService::getHexWallet($transaction['address']));

                    if ($resultTRXtransaction && $activeWallet)
                    {
                        $tokenTransaction = $this->tronService->send(CurrencyService::curencyForTronBlockchain($transaction['token']),MerchantWalletService::getPrivateKey($transaction['address']), $merchantMainWallet->number, $transaction['amount']);

                        if ($tokenTransaction)
                        {
                            $transaction->update(['status' => MerchantTransactionStatusEnum::paid]);
                        }
                    }
                }
            }

            return redirect()->back()->withInput()->withSuccess('Transaction processed successfully');
        }
    }
}

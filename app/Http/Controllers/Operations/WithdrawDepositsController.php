<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Enums\BlockChainEnum;
use App\Http\Enums\MerchantTransactionStatusEnum;
use App\Http\Enums\MerchantTypeTransactionEnum;
use App\Http\Requests\MainWalletRequest;
use App\Models\MerchantWallet;
use App\Services\Operations\CurrencyService;
use App\Services\Operations\MerchantWallet\MerchantWalletService;
use App\Services\Tron\TronHelper;
use App\Services\Tron\TronService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WithdrawDepositsController extends Controller
{
    public function __construct(TronService $tronService)
    {
        $this->tronService = $tronService;
    }
    public function withdrawDeposits(MainWalletRequest $request)
    {
        $validated = $request->validated();
Log::info('Start withdraw. Validated',[$validated]);
        $walletMerchant = MerchantWallet::query()->findOrFail($validated['wallet_id']);
Log::info('Start withdraw. WalletMerchant',[$walletMerchant]);
        $merchant = $walletMerchant->merchant;
Log::info('Start withdraw. Merchant',[$merchant]);
        $merchantMainWallet = $merchant->mainWallet()->first();
Log::info('Start withdraw. MerchantMainWallet',[$merchantMainWallet]);
        $deposits = $merchant->transactions()->where('type_transactions',MerchantTypeTransactionEnum::deposit->value)
            ->whereIn('status', [MerchantTransactionStatusEnum::successful->value,
                                 MerchantTransactionStatusEnum::withoutInitialization->value])->get();
Log::info('Start withdraw. Deposits',[$deposits]);
        //считаем комиссию
        $allCommission['total_fee'] = 0;

        $transactions = [];

        $merchantTransaction = null;

        if ($deposits->isEmpty())
        {
                return redirect()
                    ->back()
                    ->withInput()
                    ->withErrors([
                        'balance' => 'We didn\'t find deposits'
                    ]);
        }

        foreach ($deposits as $deposit)
        {
            Log::info('Start withdraw. Deposit',[$deposit]);
            $token = CurrencyService::curencyForTronBlockchain(CurrencyService::tronDBNameToken($deposit->currency_id));

            $commission =$this->tronService->estimateTRC20Fee($token,$deposit->wallet_to,$merchantMainWallet->number,$deposit->sum);

            $transactions[] = [
                                'address' => $deposit->wallet_to,
                                'amount' => $deposit->sum,
                                'token' => $token,
                                'commission' => $commission['total_fee']
            ];

            $allCommission['total_fee'] += $commission['total_fee'];

            $allCommission['fee_currency'] = $commission['fee_currency'];

            $merchantTransaction = $deposit;
        }

        $balanceMerchantMainWallet = $this->tronService->getAllBalances($merchantMainWallet->number);
Log::info('Start withdraw. BalanceMerchantMainWallet',[$balanceMerchantMainWallet]);
        if ($balanceMerchantMainWallet['balances'][$allCommission['fee_currency']] < $allCommission['total_fee'])
        {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors([
                    'balance' => 'You need to top up your balance '.$merchantMainWallet->number.' for the commission on '.$allCommission['total_fee'].$allCommission['fee_currency'],
                ]);
        }
        else
        {
            foreach($transactions as $transaction)
            {
                $realBalance = $this->tronService->getAllBalances($transaction['address']);
                //делаем транзакцию в трх
                if (bccomp($realBalance['balances'][$transaction['token']], $transaction['amount'], 8) >= 0)
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
                            $merchantTransaction->update(['status' => MerchantTransactionStatusEnum::paid->value]);
                        }
                    }
                }
                else
                {
                    $merchantTransaction->update(['status' => MerchantTransactionStatusEnum::canceled->value]);

                    //проверяем остальные балансы и перекидываем их
                    $realBalance = $this->tronService->getAllBalances($transaction['address']);

                    $currencies = BlockChainEnum::network();

                    foreach ($currencies['tron'] as $currency)
                    {
                        if (bccomp($realBalance['balances'][$currency], 0, 8) >= 0)
                        {
                            $tokenTransaction = $this->tronService->send(CurrencyService::curencyForTronBlockchain($currency),MerchantWalletService::getPrivateKey($transaction['address']), $merchantMainWallet->number, $realBalance['balances'][$currency]);
                        }
                    }
                }
            }

            return redirect()->back()->withInput()->withSuccess('Transaction processed successfully');
        }
    }
}

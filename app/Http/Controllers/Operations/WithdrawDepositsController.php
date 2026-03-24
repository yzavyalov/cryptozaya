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
//    public function withdrawDeposits(MainWalletRequest $request)
//    {
//        $validated = $request->validated();
//Log::info('Start withdraw. Validated',[$validated]);
//        $walletMerchant = MerchantWallet::query()->findOrFail($validated['wallet_id']);
//Log::info('Start withdraw. WalletMerchant',[$walletMerchant]);
//        $merchant = $walletMerchant->merchant;
//Log::info('Start withdraw. Merchant',[$merchant]);
//        $merchantMainWallet = $merchant->mainWallet()->first();
//Log::info('Start withdraw. MerchantMainWallet',[$merchantMainWallet]);
//        $deposits = $merchant->transactions()->where('type_transactions',MerchantTypeTransactionEnum::deposit->value)
//            ->whereIn('status', [MerchantTransactionStatusEnum::successful->value,
//                                 MerchantTransactionStatusEnum::withoutInitialization->value])->get();
//Log::info('Start withdraw. Deposits',[$deposits]);
//        //считаем комиссию
//        $allCommission['total_fee'] = 0;
//
//        $transactions = [];
//
//        $merchantTransaction = null;
//
//        if ($deposits->isEmpty())
//        {
//                return redirect()
//                    ->back()
//                    ->withInput()
//                    ->withErrors([
//                        'balance' => 'We didn\'t find deposits'
//                    ]);
//        }
//
//        foreach ($deposits as $deposit)
//        {
//            Log::info('Start withdraw. Deposit',[$deposit]);
//            $token = CurrencyService::curencyForTronBlockchain(CurrencyService::tronDBNameToken($deposit->currency_id));
//
//            $commission =$this->tronService->estimateTRC20Fee($token,$deposit->wallet_to,$merchantMainWallet->number,$deposit->sum);
//
//            $transactions[] = [
//                                'address' => $deposit->wallet_to,
//                                'amount' => $deposit->sum,
//                                'token' => $token,
//                                'commission' => $commission['total_fee']
//            ];
//
//            $allCommission['total_fee'] += $commission['total_fee'];
//
//            $allCommission['fee_currency'] = $commission['fee_currency'];
//
//            $merchantTransaction = $deposit;
//        }
//
//        $balanceMerchantMainWallet = $this->tronService->getAllBalances($merchantMainWallet->number);
//
//        if ($balanceMerchantMainWallet['balances'][$allCommission['fee_currency']] < $allCommission['total_fee'])
//        {
//            return redirect()
//                ->back()
//                ->withInput()
//                ->withErrors([
//                    'balance' => 'You need to top up your balance '.$merchantMainWallet->number.' for the commission on '.$allCommission['total_fee'].$allCommission['fee_currency'],
//                ]);
//        }
//        else
//        {
//            foreach($transactions as $transaction)
//            {
//                $realBalance = $this->tronService->getAllBalances($transaction['address']);
//                //делаем транзакцию в трх
//                if (bccomp($realBalance['balances'][$transaction['token']], $transaction['amount'], 8) >= 0)
//                {
//                    //делаем транзакцию в трх
//                    $trx = $this->tronService->send('TRX',$merchantMainWallet->private_key, $transaction['address'], $transaction['commission']);
//
//                    $resultTRXtransaction = $this->tronService->waitForTrxConfirmation($trx['txid']);
//                    // потом транзакцию выводим токен на кошелек мерчанта
//                    $activeWallet = $this->tronService->isAccountActivated(MerchantWalletService::getHexWallet($transaction['address']));
//
//                    if ($resultTRXtransaction && $activeWallet)
//                    {
//                        $tokenTransaction = $this->tronService->send(CurrencyService::curencyForTronBlockchain($transaction['token']),MerchantWalletService::getPrivateKey($transaction['address']), $merchantMainWallet->number, $transaction['amount']);
//
//                        if ($tokenTransaction)
//                        {
//                            $merchantTransaction->update(['status' => MerchantTransactionStatusEnum::paid->value]);
//                        }
//                    }
//                }
//                else
//                {
//                    $merchantTransaction->update(['status' => MerchantTransactionStatusEnum::canceled->value]);
//
//                    //проверяем остальные балансы и перекидываем их
//                    $realBalance = $this->tronService->getAllBalances($transaction['address']);
//
//                    $currencies = BlockChainEnum::network();
//
//                    foreach ($currencies['tron'] as $currency)
//                    {
//                        if (bccomp($realBalance['balances'][$currency], 0, 8) >= 0)
//                        {
//                            $tokenTransaction = $this->tronService->send(CurrencyService::curencyForTronBlockchain($currency),MerchantWalletService::getPrivateKey($transaction['address']), $merchantMainWallet->number, $realBalance['balances'][$currency]);
//                        }
//                    }
//                }
//            }
//
//            return redirect()->back()->withInput()->withSuccess('Transaction processed successfully');
//        }
//    }

    public function withdrawDeposits(MainWalletRequest $request)
    {
        $validated = $request->validated();

        Log::info('Withdraw grouped deposits started', [
            'validated' => $validated,
        ]);

        $walletMerchant = MerchantWallet::query()->findOrFail($validated['wallet_id']);
        $merchant = $walletMerchant->merchant;
        $merchantMainWallet = $merchant->mainWallet()->first();

        if (!$merchantMainWallet) {
            Log::warning('Withdraw aborted: main wallet not found', [
                'wallet_id' => $validated['wallet_id'],
                'merchant_id' => $merchant->id ?? null,
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->withErrors([
                    'wallet' => 'Main wallet not found',
                ]);
        }

        $deposits = $merchant->transactions()
            ->where('type_transactions', MerchantTypeTransactionEnum::deposit->value)
            ->whereIn('status', [
                MerchantTransactionStatusEnum::successful->value,
                MerchantTransactionStatusEnum::withoutInitialization->value,
            ])
            ->orderBy('id')
            ->get();

        Log::info('Withdraw grouped deposits fetched', [
            'merchant_id' => $merchant->id ?? null,
            'count' => $deposits->count(),
        ]);

        if ($deposits->isEmpty()) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors([
                    'balance' => 'We didn\'t find deposits',
                ]);
        }

        $groupedDeposits = $deposits->groupBy(function ($deposit) {
            return $deposit->wallet_to . '|' . $deposit->currency_id;
        });

        $processedGroups = 0;
        $failedGroups = 0;
        $paidDeposits = 0;

        foreach ($groupedDeposits as $groupKey => $walletDeposits) {
            $walletAddress = null;
            $currencyId = null;

            try {
                $firstDeposit = $walletDeposits->first();

                if (!$firstDeposit) {
                    Log::warning('Grouped deposits has no first deposit', [
                        'group_key' => $groupKey,
                    ]);
                    $failedGroups++;
                    continue;
                }

                $walletAddress = $firstDeposit->wallet_to;
                $currencyId = $firstDeposit->currency_id;

                $token = CurrencyService::curencyForTronBlockchain(
                    CurrencyService::tronDBNameToken($currencyId)
                );

                Log::info('Processing grouped wallet/currency', [
                    'group_key' => $groupKey,
                    'wallet' => $walletAddress,
                    'currency_id' => $currencyId,
                    'token' => $token,
                    'deposits_count' => $walletDeposits->count(),
                    'deposit_ids' => $walletDeposits->pluck('id')->toArray(),
                ]);

                $realBalance = $this->tronService->getAllBalances($walletAddress);
                $walletTokenBalance = (string) ($realBalance['balances'][$token] ?? '0');

                Log::info('Grouped wallet token balance checked', [
                    'group_key' => $groupKey,
                    'wallet' => $walletAddress,
                    'token' => $token,
                    'balance' => $walletTokenBalance,
                ]);

                if (bccomp($walletTokenBalance, '0', 8) <= 0) {
                    Log::warning('Grouped wallet token balance is zero, statuses not changed', [
                        'group_key' => $groupKey,
                        'wallet' => $walletAddress,
                        'token' => $token,
                    ]);

                    $failedGroups++;
                    continue;
                }

                $commission = $this->tronService->estimateTRC20Fee(
                    $token,
                    $walletAddress,
                    $merchantMainWallet->number,
                    $walletTokenBalance
                );

                if (!is_array($commission) || !isset($commission['total_fee'], $commission['fee_currency'])) {
                    Log::error('Invalid commission response for grouped wallet/token, statuses not changed', [
                        'group_key' => $groupKey,
                        'wallet' => $walletAddress,
                        'token' => $token,
                        'commission' => $commission,
                    ]);

                    $failedGroups++;
                    continue;
                }

                $commissionAmount = (string) $commission['total_fee'];
                $feeCurrency = $commission['fee_currency'];

                $mainWalletBalances = $this->tronService->getAllBalances($merchantMainWallet->number);
                $mainFeeBalance = (string) ($mainWalletBalances['balances'][$feeCurrency] ?? '0');

                Log::info('Main wallet fee balance checked', [
                    'group_key' => $groupKey,
                    'main_wallet' => $merchantMainWallet->number,
                    'fee_currency' => $feeCurrency,
                    'fee_balance' => $mainFeeBalance,
                    'required_fee' => $commissionAmount,
                ]);

                if (bccomp($mainFeeBalance, $commissionAmount, 8) < 0) {
                    Log::warning('Not enough fee balance on main wallet, statuses not changed', [
                        'group_key' => $groupKey,
                        'main_wallet' => $merchantMainWallet->number,
                        'wallet' => $walletAddress,
                        'token' => $token,
                        'required_fee' => $commissionAmount,
                        'fee_currency' => $feeCurrency,
                        'available' => $mainFeeBalance,
                    ]);

                    $failedGroups++;
                    continue;
                }

                $trx = $this->tronService->send(
                    'TRX',
                    $merchantMainWallet->private_key,
                    $walletAddress,
                    $commissionAmount
                );

                if (!$trx || !isset($trx['txid'])) {
                    Log::error('TRX fee transfer failed, statuses not changed', [
                        'group_key' => $groupKey,
                        'wallet' => $walletAddress,
                        'response' => $trx,
                    ]);

                    $failedGroups++;
                    continue;
                }

                Log::info('TRX fee sent to grouped wallet/token', [
                    'group_key' => $groupKey,
                    'wallet' => $walletAddress,
                    'txid' => $trx['txid'],
                    'amount' => $commissionAmount,
                ]);

                $trxConfirmed = $this->tronService->waitForTrxConfirmation($trx['txid']);
                $walletActivated = $this->tronService->isAccountActivated(
                    MerchantWalletService::getHexWallet($walletAddress)
                );

                Log::info('Grouped wallet/token trx confirmation checked', [
                    'group_key' => $groupKey,
                    'wallet' => $walletAddress,
                    'trx_confirmed' => $trxConfirmed,
                    'wallet_activated' => $walletActivated,
                ]);

                if (!$trxConfirmed || !$walletActivated) {
                    Log::warning('TRX transfer not confirmed or wallet is not activated, statuses not changed', [
                        'group_key' => $groupKey,
                        'wallet' => $walletAddress,
                        'trx_confirmed' => $trxConfirmed,
                        'wallet_activated' => $walletActivated,
                    ]);

                    $failedGroups++;
                    continue;
                }

                $privateKey = MerchantWalletService::getPrivateKey($walletAddress);

                if (!$privateKey) {
                    Log::error('Private key for grouped wallet not found, statuses not changed', [
                        'group_key' => $groupKey,
                        'wallet' => $walletAddress,
                    ]);

                    $failedGroups++;
                    continue;
                }

                $tokenTransaction = $this->tronService->send(
                    $token,
                    $privateKey,
                    $merchantMainWallet->number,
                    $walletTokenBalance
                );

                if (!$tokenTransaction) {
                    Log::error('Token transfer failed, statuses not changed', [
                        'group_key' => $groupKey,
                        'wallet' => $walletAddress,
                        'token' => $token,
                        'amount' => $walletTokenBalance,
                        'response' => $tokenTransaction,
                    ]);

                    $failedGroups++;
                    continue;
                }

                foreach ($walletDeposits as $deposit) {
                    $deposit->update([
                        'status' => MerchantTransactionStatusEnum::paid->value,
                    ]);
                }

                $processedGroups++;
                $paidDeposits += $walletDeposits->count();

                Log::info('Grouped wallet/token processed successfully', [
                    'group_key' => $groupKey,
                    'wallet' => $walletAddress,
                    'token' => $token,
                    'amount' => $walletTokenBalance,
                    'deposits_paid' => $walletDeposits->pluck('id')->toArray(),
                    'token_transaction' => $tokenTransaction,
                ]);
            } catch (\Throwable $e) {
                Log::error('Grouped wallet/token withdraw failed, statuses not changed', [
                    'group_key' => $groupKey,
                    'wallet' => $walletAddress,
                    'currency_id' => $currencyId,
                    'deposit_ids' => $walletDeposits->pluck('id')->toArray(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $failedGroups++;
                continue;
            }
        }

        return redirect()
            ->back()
            ->withInput()
            ->withSuccess(
                "Groups processed: {$processedGroups}, groups failed: {$failedGroups}, deposits paid: {$paidDeposits}"
            );
    }
}

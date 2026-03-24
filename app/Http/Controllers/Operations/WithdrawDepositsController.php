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

        $preparedGroups = [];
        $totalRequiredFeeByCurrency = [];

        foreach ($groupedDeposits as $groupKey => $walletDeposits) {
            try {
                $firstDeposit = $walletDeposits->first();

                if (!$firstDeposit) {
                    Log::warning('Grouped deposits has no first deposit', [
                        'group_key' => $groupKey,
                    ]);
                    continue;
                }

                $walletAddress = $firstDeposit->wallet_to;
                $currencyId = $firstDeposit->currency_id;

                $token = CurrencyService::curencyForTronBlockchain(
                    CurrencyService::tronDBNameToken($currencyId)
                );

                Log::info('Preparing grouped wallet/currency', [
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
                    Log::warning('Grouped wallet token balance is zero, skipped', [
                        'group_key' => $groupKey,
                        'wallet' => $walletAddress,
                        'token' => $token,
                    ]);
                    continue;
                }

                $commission = $this->tronService->estimateTRC20Fee(
                    $token,
                    $walletAddress,
                    $merchantMainWallet->number,
                    $walletTokenBalance
                );

                if (!is_array($commission) || !isset($commission['total_fee'], $commission['fee_currency'])) {
                    Log::error('Invalid commission response for grouped wallet/token, skipped', [
                        'group_key' => $groupKey,
                        'wallet' => $walletAddress,
                        'token' => $token,
                        'commission' => $commission,
                    ]);
                    continue;
                }

                $commissionAmount = (string) $commission['total_fee'];
                $feeCurrency = (string) $commission['fee_currency'];

                if (!isset($totalRequiredFeeByCurrency[$feeCurrency])) {
                    $totalRequiredFeeByCurrency[$feeCurrency] = '0';
                }

                $totalRequiredFeeByCurrency[$feeCurrency] = bcadd(
                    $totalRequiredFeeByCurrency[$feeCurrency],
                    $commissionAmount,
                    8
                );

                $preparedGroups[] = [
                    'group_key' => $groupKey,
                    'wallet' => $walletAddress,
                    'currency_id' => $currencyId,
                    'token' => $token,
                    'wallet_balance' => $walletTokenBalance,
                    'commission_amount' => $commissionAmount,
                    'fee_currency' => $feeCurrency,
                    'deposits' => $walletDeposits,
                ];
            } catch (\Throwable $e) {
                Log::error('Failed to prepare grouped wallet/token, skipped', [
                    'group_key' => $groupKey,
                    'deposit_ids' => $walletDeposits->pluck('id')->toArray(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                continue;
            }
        }

        if (empty($preparedGroups)) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors([
                    'balance' => 'No wallets with positive balance were found for withdrawal',
                ]);
        }

        $mainWalletBalances = $this->tronService->getAllBalances($merchantMainWallet->number);

        $missingFeeByCurrency = [];

        foreach ($totalRequiredFeeByCurrency as $currency => $requiredAmount) {
            $availableAmount = (string) ($mainWalletBalances['balances'][$currency] ?? '0');

            Log::info('Main wallet total fee check', [
                'main_wallet' => $merchantMainWallet->number,
                'currency' => $currency,
                'required_total_fee' => $requiredAmount,
                'available_total_fee' => $availableAmount,
            ]);

            if (bccomp($availableAmount, $requiredAmount, 8) < 0) {
                $missingFeeByCurrency[$currency] = bcsub($requiredAmount, $availableAmount, 8);
            }
        }

        if (!empty($missingFeeByCurrency)) {
            $parts = [];

            foreach ($missingFeeByCurrency as $currency => $amount) {
                if (bccomp($amount, '0', 8) > 0) {
                    $parts[] = "{$amount} {$currency}";
                }
            }

            $message = 'Top up main wallet ' . $merchantMainWallet->number . ' by: ' . implode(', ', $parts);

            Log::warning('Not enough total fee balance on main wallet', [
                'main_wallet' => $merchantMainWallet->number,
                'missing' => $missingFeeByCurrency,
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->withErrors([
                    'balance' => $message,
                ]);
        }

        $processedGroups = 0;
        $failedGroups = 0;
        $paidDeposits = 0;

        foreach ($preparedGroups as $group) {
            try {
                Log::info('Processing grouped wallet/token', [
                    'group_key' => $group['group_key'],
                    'wallet' => $group['wallet'],
                    'token' => $group['token'],
                    'wallet_balance' => $group['wallet_balance'],
                    'commission_amount' => $group['commission_amount'],
                    'deposit_ids' => $group['deposits']->pluck('id')->toArray(),
                ]);

                $trx = $this->tronService->send(
                    'TRX',
                    $merchantMainWallet->private_key,
                    $group['wallet'],
                    $group['commission_amount']
                );

                if (!$trx || !isset($trx['txid'])) {
                    Log::error('TRX fee transfer failed, statuses not changed', [
                        'group_key' => $group['group_key'],
                        'wallet' => $group['wallet'],
                        'response' => $trx,
                    ]);

                    $failedGroups++;
                    continue;
                }

                Log::info('TRX fee sent to grouped wallet/token', [
                    'group_key' => $group['group_key'],
                    'wallet' => $group['wallet'],
                    'txid' => $trx['txid'],
                    'amount' => $group['commission_amount'],
                ]);

                $trxConfirmed = $this->tronService->waitForTrxConfirmation($trx['txid']);
                $walletActivated = $this->tronService->isAccountActivated(
                    MerchantWalletService::getHexWallet($group['wallet'])
                );

                Log::info('Grouped wallet/token trx confirmation checked', [
                    'group_key' => $group['group_key'],
                    'wallet' => $group['wallet'],
                    'trx_confirmed' => $trxConfirmed,
                    'wallet_activated' => $walletActivated,
                ]);

                if (!$trxConfirmed || !$walletActivated) {
                    Log::warning('TRX transfer not confirmed or wallet is not activated, statuses not changed', [
                        'group_key' => $group['group_key'],
                        'wallet' => $group['wallet'],
                        'trx_confirmed' => $trxConfirmed,
                        'wallet_activated' => $walletActivated,
                    ]);

                    $failedGroups++;
                    continue;
                }

                $privateKey = MerchantWalletService::getPrivateKey($group['wallet']);

                if (!$privateKey) {
                    Log::error('Private key for grouped wallet not found, statuses not changed', [
                        'group_key' => $group['group_key'],
                        'wallet' => $group['wallet'],
                    ]);

                    $failedGroups++;
                    continue;
                }

                $tokenTransaction = $this->tronService->send(
                    $group['token'],
                    $privateKey,
                    $merchantMainWallet->number,
                    $group['wallet_balance']
                );

                if (!$tokenTransaction) {
                    Log::error('Token transfer failed, statuses not changed', [
                        'group_key' => $group['group_key'],
                        'wallet' => $group['wallet'],
                        'token' => $group['token'],
                        'amount' => $group['wallet_balance'],
                        'response' => $tokenTransaction,
                    ]);

                    $failedGroups++;
                    continue;
                }

                foreach ($group['deposits'] as $deposit) {
                    $deposit->update([
                        'status' => MerchantTransactionStatusEnum::paid->value,
                    ]);
                }

                $processedGroups++;
                $paidDeposits += $group['deposits']->count();

                Log::info('Grouped wallet/token processed successfully', [
                    'group_key' => $group['group_key'],
                    'wallet' => $group['wallet'],
                    'token' => $group['token'],
                    'amount' => $group['wallet_balance'],
                    'deposits_paid' => $group['deposits']->pluck('id')->toArray(),
                    'token_transaction' => $tokenTransaction,
                ]);
            } catch (\Throwable $e) {
                Log::error('Grouped wallet/token withdraw failed, statuses not changed', [
                    'group_key' => $group['group_key'],
                    'wallet' => $group['wallet'],
                    'currency_id' => $group['currency_id'],
                    'deposit_ids' => $group['deposits']->pluck('id')->toArray(),
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

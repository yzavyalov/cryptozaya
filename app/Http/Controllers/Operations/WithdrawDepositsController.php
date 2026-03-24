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

        Log::info('Withdraw deposits started', [
            'validated' => $validated,
        ]);

        $walletMerchant = MerchantWallet::query()->findOrFail($validated['wallet_id']);
        $merchant = $walletMerchant->merchant;
        $merchantMainWallet = $merchant->mainWallet()->first();

        if (!$merchantMainWallet) {
            Log::warning('Withdraw deposits aborted: main wallet not found', [
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
            ->get();

        Log::info('Withdraw deposits fetched', [
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

        $transactions = [];
        $allCommission = [
            'total_fee' => '0',
            'fee_currency' => 'TRX',
        ];

        foreach ($deposits as $deposit) {
            Log::info('Preparing deposit for withdraw', [
                'deposit_id' => $deposit->id,
                'wallet_to' => $deposit->wallet_to,
                'sum' => $deposit->sum,
                'currency_id' => $deposit->currency_id,
                'status' => $deposit->status,
            ]);

            $token = CurrencyService::curencyForTronBlockchain(
                CurrencyService::tronDBNameToken($deposit->currency_id)
            );

            $commission = $this->tronService->estimateTRC20Fee(
                $token,
                $deposit->wallet_to,
                $merchantMainWallet->number,
                $deposit->sum
            );

            if (!is_array($commission) || !isset($commission['total_fee'], $commission['fee_currency'])) {
                Log::error('Invalid commission response', [
                    'deposit_id' => $deposit->id,
                    'commission' => $commission,
                ]);

                return redirect()
                    ->back()
                    ->withInput()
                    ->withErrors([
                        'balance' => 'Failed to estimate commission',
                    ]);
            }

            $transactions[] = [
                'deposit' => $deposit,
                'deposit_id' => $deposit->id,
                'address' => $deposit->wallet_to,
                'amount' => (string) $deposit->sum,
                'token' => $token,
                'commission' => (string) $commission['total_fee'],
                'fee_currency' => $commission['fee_currency'],
            ];

            $allCommission['total_fee'] = bcadd(
                $allCommission['total_fee'],
                (string) $commission['total_fee'],
                8
            );

            $allCommission['fee_currency'] = $commission['fee_currency'];
        }

        $balanceMerchantMainWallet = $this->tronService->getAllBalances($merchantMainWallet->number);

        Log::info('Main wallet balances received', [
            'merchant_main_wallet' => $merchantMainWallet->number,
            'balances' => $balanceMerchantMainWallet,
        ]);

        $mainFeeBalance = (string) ($balanceMerchantMainWallet['balances'][$allCommission['fee_currency']] ?? '0');

        if (bccomp($mainFeeBalance, $allCommission['total_fee'], 8) < 0) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors([
                    'balance' => 'You need to top up your balance ' .
                        $merchantMainWallet->number .
                        ' for the commission on ' .
                        $allCommission['total_fee'] . ' ' .
                        $allCommission['fee_currency'],
                ]);
        }

        $processed = 0;
        $failed = 0;

        foreach ($transactions as $transaction) {
            $deposit = $transaction['deposit'];

            try {
                Log::info('Processing deposit withdraw', [
                    'deposit_id' => $transaction['deposit_id'],
                    'address' => $transaction['address'],
                    'amount' => $transaction['amount'],
                    'token' => $transaction['token'],
                    'commission' => $transaction['commission'],
                ]);

                $deposit->update([
                    'status' => MerchantTransactionStatusEnum::processing->value,
                ]);

                $realBalance = $this->tronService->getAllBalances($transaction['address']);
                $tokenBalance = (string) ($realBalance['balances'][$transaction['token']] ?? '0');

                Log::info('Deposit wallet balance checked', [
                    'deposit_id' => $transaction['deposit_id'],
                    'wallet' => $transaction['address'],
                    'token' => $transaction['token'],
                    'token_balance' => $tokenBalance,
                    'required_amount' => $transaction['amount'],
                ]);

                if (bccomp($tokenBalance, $transaction['amount'], 8) >= 0) {
                    $trx = $this->tronService->send(
                        'TRX',
                        $merchantMainWallet->private_key,
                        $transaction['address'],
                        $transaction['commission']
                    );

                    if (!$trx || !isset($trx['txid'])) {
                        throw new \RuntimeException('TRX fee transfer failed');
                    }

                    Log::info('TRX fee sent', [
                        'deposit_id' => $transaction['deposit_id'],
                        'txid' => $trx['txid'],
                    ]);

                    $resultTRXtransaction = $this->tronService->waitForTrxConfirmation($trx['txid']);
                    $activeWallet = $this->tronService->isAccountActivated(
                        MerchantWalletService::getHexWallet($transaction['address'])
                    );

                    Log::info('TRX confirmation / wallet activation checked', [
                        'deposit_id' => $transaction['deposit_id'],
                        'trx_confirmed' => $resultTRXtransaction,
                        'wallet_activated' => $activeWallet,
                    ]);

                    if (!$resultTRXtransaction || !$activeWallet) {
                        throw new \RuntimeException('TRX transfer not confirmed or wallet is not activated');
                    }

                    $privateKey = MerchantWalletService::getPrivateKey($transaction['address']);

                    if (!$privateKey) {
                        throw new \RuntimeException('Private key for deposit wallet not found');
                    }

                    $tokenTransaction = $this->tronService->send(
                        $transaction['token'],
                        $privateKey,
                        $merchantMainWallet->number,
                        $transaction['amount']
                    );

                    if (!$tokenTransaction) {
                        throw new \RuntimeException('Token transfer failed');
                    }

                    $deposit->update([
                        'status' => MerchantTransactionStatusEnum::paid->value,
                    ]);

                    $processed++;

                    Log::info('Deposit successfully withdrawn', [
                        'deposit_id' => $transaction['deposit_id'],
                        'token_transaction' => $tokenTransaction,
                    ]);
                } else {
                    Log::warning('Insufficient token balance on deposit wallet', [
                        'deposit_id' => $transaction['deposit_id'],
                        'wallet' => $transaction['address'],
                        'token' => $transaction['token'],
                        'balance' => $tokenBalance,
                        'required' => $transaction['amount'],
                    ]);

                    $deposit->update([
                        'status' => MerchantTransactionStatusEnum::canceled->value,
                    ]);

                    $realBalance = $this->tronService->getAllBalances($transaction['address']);
                    $currencies = BlockChainEnum::network();

                    foreach ($currencies['tron'] as $currency) {
                        $balance = (string) ($realBalance['balances'][$currency] ?? '0');

                        if (bccomp($balance, '0', 8) > 0) {
                            try {
                                $privateKey = MerchantWalletService::getPrivateKey($transaction['address']);

                                if (!$privateKey) {
                                    Log::warning('Private key not found for sweeping balance', [
                                        'deposit_id' => $transaction['deposit_id'],
                                        'wallet' => $transaction['address'],
                                        'currency' => $currency,
                                    ]);
                                    continue;
                                }

                                $this->tronService->send(
                                    CurrencyService::curencyForTronBlockchain($currency),
                                    $privateKey,
                                    $merchantMainWallet->number,
                                    $balance
                                );

                                Log::info('Remaining balance swept', [
                                    'deposit_id' => $transaction['deposit_id'],
                                    'currency' => $currency,
                                    'amount' => $balance,
                                ]);
                            } catch (\Throwable $e) {
                                Log::error('Failed to sweep remaining balance', [
                                    'deposit_id' => $transaction['deposit_id'],
                                    'currency' => $currency,
                                    'amount' => $balance,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }
                    }

                    $failed++;
                }
            } catch (\Throwable $e) {
                $failed++;

                Log::error('Deposit withdraw failed', [
                    'deposit_id' => $transaction['deposit_id'],
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $deposit->update([
                    'status' => MerchantTransactionStatusEnum::canceled->value,
                ]);
            }
        }

        return redirect()
            ->back()
            ->withInput()
            ->withSuccess("Processed: {$processed}, failed: {$failed}");
    }
}

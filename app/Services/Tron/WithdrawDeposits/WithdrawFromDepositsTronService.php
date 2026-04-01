<?php

namespace App\Services\Tron\WithdrawDeposits;

use App\DTO\Withdraw\WithdrawPlanDto;
use App\Http\Enums\MerchantTransactionStatusEnum;
use App\Services\Tron\TronWalletBalanceService;
use Throwable;

class WithdrawFromDepositsTronService
{
    public function __construct(
        protected CalculationCommissionService $commissionService,
        protected TronWalletBalanceService $tronWalletBalanceService,
        protected SendTronDepositToMainWalletService $sendTronDepositToMainWalletService,
        protected TransferTRXfromMainWalletToDepositWalletService $transferTRXfromMainWalletToDepositWalletService,
        protected CheckAnotherDepositsForWalletService $checkAnotherDepositsForWalletService,
    ) {
    }

    public function depositsPlan($deposits, $merchantMainWallet): WithdrawPlanDto
    {
        try {
            if (!$merchantMainWallet) {
                return WithdrawPlanDto::error('Основной кошелек мерчанта не найден.');
            }

            $mainBalance = $this->tronWalletBalanceService
                ->tronService
                ->getAllBalances($merchantMainWallet->number);

            $mainTRXBalance = (float) ($mainBalance['balances']['TRX'] ?? 0);

            $processed = [];
            $skipped = [];
            $totalRequiredFeeByCurrency = [
                'TRX' => 0.0,
            ];
            $missingFeeByCurrency = [
                'TRX' => 0.0,
            ];

            foreach ($deposits as $deposit) {
                if ($deposit->network !== 'tron') {
                    continue;
                }

                $checkBalance = $this->tronWalletBalanceService->checkBalance($deposit->wallet_to);

                $trxBalance = (float) ($checkBalance['TRX_balance'] ?? 0);
                $usdtBalance = (float) ($checkBalance['USDT_balance'] ?? 0);
                $usdcBalance = (float) ($checkBalance['USDC_balance'] ?? 0);
                $needCommission = (bool) ($checkBalance['need_commission'] ?? false);

                if (
                    !$needCommission &&
                    $trxBalance == 0.0 &&
                    $usdtBalance == 0.0 &&
                    $usdcBalance == 0.0
                ) {
                    $deposit->update([
                        'status' => MerchantTransactionStatusEnum::zeroBalance->value,
                        'sum' => 0,
                    ]);

                    $skipped[] = [
                        'deposit_id' => $deposit->id,
                        'wallet' => $deposit->wallet_to,
                        'status' => MerchantTransactionStatusEnum::zeroBalance->value,
                        'reason' => 'Нулевой баланс',
                    ];

                    continue;
                }

                if (!$needCommission) {
                    $balance = [
                        'TRX_balance' => $trxBalance,
                        'USDT_balance' => $usdtBalance,
                        'USDC_balance' => $usdcBalance,
                    ];

                    $this->sendTronDepositToMainWalletService->send(
                        $balance,
                        $merchantMainWallet->number,
                        $deposit->wallet_to
                    );

                    $deposit->update([
                        'status' => MerchantTransactionStatusEnum::toMainWallet->value,
                    ]);

                    $this->checkAnotherDepositsForWalletService->checkDeposits(
                        $deposits,
                        $deposit->wallet_to
                    );

                    $processed[] = [
                        'deposit_id' => $deposit->id,
                        'wallet' => $deposit->wallet_to,
                        'status' => MerchantTransactionStatusEnum::toMainWallet->value,
                        'trx_added' => 0.0,
                    ];

                    continue;
                }

                $commission = $this->commissionService->calculateCommission(
                    $merchantMainWallet->number,
                    $deposit->wallet_to,
                    $trxBalance,
                    $usdtBalance,
                    $usdcBalance
                );

                $difference = (float) (abs($commission['difference']) ?? 0.0);
                $enoughBalance = (bool) ($commission['enough_balance'] ?? false);

                $totalRequiredFeeByCurrency['TRX'] += $difference;

                if ($enoughBalance) {
                    $balance = [
                        'TRX_balance' => $trxBalance,
                        'USDT_balance' => $usdtBalance,
                        'USDC_balance' => $usdcBalance,
                    ];

                    $this->sendTronDepositToMainWalletService->send(
                        $balance,
                        $merchantMainWallet->number,
                        $deposit->wallet_to
                    );

                    $deposit->update([
                        'status' => MerchantTransactionStatusEnum::toMainWallet->value,
                    ]);

                    $this->checkAnotherDepositsForWalletService->checkDeposits(
                        $deposits,
                        $deposit->wallet_to
                    );

                    $processed[] = [
                        'deposit_id' => $deposit->id,
                        'wallet' => $deposit->wallet_to,
                        'status' => MerchantTransactionStatusEnum::toMainWallet->value,
                        'trx_added' => 0.0,
                    ];

                    continue;
                }

                if ($mainTRXBalance >= $difference) {
                    $this->transferTRXfromMainWalletToDepositWalletService
                        ->TransferTRXfromMainWalletToDepositWallet(
                            $merchantMainWallet,
                            $deposit->wallet_to,
                            $difference
                        );

                    $mainTRXBalance -= $difference;

                    $balance = [
                        'TRX_balance' => $trxBalance,
                        'USDT_balance' => $usdtBalance,
                        'USDC_balance' => $usdcBalance,
                    ];

                    $this->sendTronDepositToMainWalletService->send(
                        $balance,
                        $merchantMainWallet->number,
                        $deposit->wallet_to
                    );

                    $deposit->update([
                        'status' => MerchantTransactionStatusEnum::toMainWallet->value,
                    ]);

                    $this->checkAnotherDepositsForWalletService->checkDeposits(
                        $deposits,
                        $deposit->wallet_to
                    );

                    $processed[] = [
                        'deposit_id' => $deposit->id,
                        'wallet' => $deposit->wallet_to,
                        'status' => MerchantTransactionStatusEnum::toMainWallet->value,
                        'trx_added' => $difference,
                    ];

                    continue;
                }

                $missingFeeByCurrency['TRX'] += $difference;

                $skipped[] = [
                    'deposit_id' => $deposit->id,
                    'wallet' => $deposit->wallet_to,
                    'status' => 'waiting_for_top_up',
                    'missing_trx' => $difference,
                    'reason' => 'Недостаточно TRX на основном кошельке мерчанта',
                ];
            }

            if ($missingFeeByCurrency['TRX'] > 0) {
                return WithdrawPlanDto::partial(
                    merchantMainWallet: $merchantMainWallet,
                    processed: $processed,
                    skipped: $skipped,
                    totalRequiredFeeByCurrency: $totalRequiredFeeByCurrency,
                    missingFeeByCurrency: $missingFeeByCurrency,
                    message: "Частичный успех. Добавьте {$missingFeeByCurrency['TRX']} TRX на кошелек мерчанта и повторите выплаты.",
                );
            }

            return WithdrawPlanDto::success(
                merchantMainWallet: $merchantMainWallet,
                processed: $processed,
                skipped: $skipped,
                totalRequiredFeeByCurrency: $totalRequiredFeeByCurrency,
                message: 'Все выплаты успешно обработаны.',
            );
        } catch (Throwable $e) {
            return WithdrawPlanDto::error($e->getMessage());
        }
    }
}

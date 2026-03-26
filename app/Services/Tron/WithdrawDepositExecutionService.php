<?php

namespace App\Services\Tron;

use App\DTO\Withdraw\WithdrawExecutionResultDto;
use App\DTO\Withdraw\WithdrawGroupDto;
use App\DTO\Withdraw\WithdrawPlanDto;
use App\Http\Enums\MerchantTransactionStatusEnum;
use App\Services\Operations\MerchantWallet\MerchantWalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WithdrawDepositExecutionService
{
    public function __construct(
        private readonly TronService $tronService,
    ) {}

    public function execute(WithdrawPlanDto $plan): WithdrawExecutionResultDto
    {
        $processedGroups = 0;
        $failedGroups = 0;
        $paidDeposits = 0;
        $errors = [];

        foreach ($plan->groups as $group) {
            try {
                $this->processGroup($group, $plan);

                $processedGroups++;
                $paidDeposits += $group->depositsCount();
            } catch (\Throwable $e) {
                $failedGroups++;

                $errors[] = [
                    'group_key' => $group->groupKey,
                    'wallet' => $group->wallet,
                    'error' => $e->getMessage(),
                ];

                Log::error('Grouped wallet/token withdraw failed', [
                    'group_key' => $group->groupKey,
                    'wallet' => $group->wallet,
                    'currency_id' => $group->currencyId,
                    'deposit_ids' => $group->depositIds(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return new WithdrawExecutionResultDto(
            processedGroups: $processedGroups,
            failedGroups: $failedGroups,
            paidDeposits: $paidDeposits,
            errors: $errors,
        );
    }

    private function processGroup(WithdrawGroupDto $group, WithdrawPlanDto $plan): void
    {
        Log::info('Processing grouped wallet/token', [
            'group_key' => $group->groupKey,
            'wallet' => $group->wallet,
            'token' => $group->token,
            'wallet_balance' => $group->walletBalance,
            'commission_amount' => $group->commissionAmount,
            'deposit_ids' => $group->depositIds(),
        ]);

        $feeTx = $this->sendFeeToDepositWallet($group, $plan);
        $this->assertFeeTransferConfirmed($group, $feeTx['txid']);

        $privateKey = MerchantWalletService::getPrivateKey($group->wallet);

        if (!$privateKey) {
            throw new \RuntimeException('Private key for grouped wallet not found');
        }

        $tokenTx = $this->withdrawTokenToMainWallet($group, $plan, $privateKey);
        $this->assertTokenTransferConfirmed($group, $tokenTx['txid']);

        $this->markDepositsAsPaid($group);

        Log::info('Grouped wallet/token processed successfully', [
            'group_key' => $group->groupKey,
            'wallet' => $group->wallet,
            'token' => $group->token,
            'amount' => $group->walletBalance,
            'deposits_paid' => $group->depositIds(),
            'token_transaction' => $tokenTx,
        ]);
    }

    private function sendFeeToDepositWallet(WithdrawGroupDto $group, WithdrawPlanDto $plan): array
    {
        $trx = $this->tronService->send(
            'TRX',
            $plan->merchantMainWallet->private_key,
            $group->wallet,
            $group->commissionAmount
        );

        if (!$trx || !isset($trx['txid'])) {
            Log::error('TRX fee transfer failed, statuses not changed', [
                'group_key' => $group->groupKey,
                'wallet' => $group->wallet,
                'response' => $trx,
            ]);

            throw new \RuntimeException('TRX fee transfer failed');
        }

        Log::info('TRX fee sent to grouped wallet/token', [
            'group_key' => $group->groupKey,
            'wallet' => $group->wallet,
            'txid' => $trx['txid'],
            'amount' => $group->commissionAmount,
        ]);

        return $trx;
    }

    private function assertFeeTransferConfirmed(WithdrawGroupDto $group, string $txid): void
    {
        $trxConfirmed = $this->tronService->waitForTrxConfirmation($txid);
        $walletActivated = $this->tronService->isAccountActivated(
            MerchantWalletService::getHexWallet($group->wallet)
        );

        Log::info('Grouped wallet/token trx confirmation checked', [
            'group_key' => $group->groupKey,
            'wallet' => $group->wallet,
            'trx_confirmed' => $trxConfirmed,
            'wallet_activated' => $walletActivated,
        ]);

        if (!$trxConfirmed || !$walletActivated) {
            Log::warning('TRX transfer not confirmed or wallet is not activated, statuses not changed', [
                'group_key' => $group->groupKey,
                'wallet' => $group->wallet,
                'trx_confirmed' => $trxConfirmed,
                'wallet_activated' => $walletActivated,
            ]);

            throw new \RuntimeException('TRX transfer not confirmed or wallet is not activated');
        }
    }

    private function withdrawTokenToMainWallet(WithdrawGroupDto $group, WithdrawPlanDto $plan, string $privateKey): array
    {
        $tokenTransaction = $this->tronService->send(
            $group->token,
            $privateKey,
            $plan->merchantMainWallet->number,
            $group->walletBalance
        );

        if (!$tokenTransaction || !isset($tokenTransaction['txid'])) {
            Log::error('Token transfer failed, statuses not changed', [
                'group_key' => $group->groupKey,
                'wallet' => $group->wallet,
                'token' => $group->token,
                'amount' => $group->walletBalance,
                'response' => $tokenTransaction,
            ]);

            throw new \RuntimeException('Token transfer failed');
        }

        return $tokenTransaction;
    }

    private function assertTokenTransferConfirmed(WithdrawGroupDto $group, string $txid): void
    {
        $tokenConfirmed = $this->tronService->waitForTrxConfirmation($txid);

        if (!$tokenConfirmed) {
            Log::warning('Token transfer not confirmed, statuses not changed', [
                'group_key' => $group->groupKey,
                'wallet' => $group->wallet,
                'txid' => $txid,
            ]);

            throw new \RuntimeException('Token transfer not confirmed');
        }
    }

    private function markDepositsAsPaid(WithdrawGroupDto $group): void
    {
        DB::transaction(function () use ($group) {
            foreach ($group->deposits as $deposit) {
                $deposit->update([
                    'status' => MerchantTransactionStatusEnum::paid->value,
                ]);
            }
        });
    }
}

<?php

namespace App\Services\Tron;

use App\DTO\Withdraw\WithdrawPlanDto;
use App\Http\Enums\MerchantTransactionStatusEnum;
use App\Http\Enums\MerchantTypeTransactionEnum;
use App\Models\MerchantWallet;
use App\Services\Tron\WithdrawDeposits\WithdrawFromDepositsTronService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Throwable;

class WithdrawDepositPreparationService
{
    public function __construct(
        private readonly TronService $tronService,
        private readonly WithdrawFromDepositsTronService $withdrawFromDepositsTronService,
    ) {
    }

    public function buildWithdrawPlan(int $walletId): WithdrawPlanDto
    {
        try {
            $merchantMainWallet = MerchantWallet::query()->findOrFail($walletId);
            $merchant = $merchantMainWallet->merchant;

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
                return WithdrawPlanDto::error('We didn\'t find deposits');
            }

            return $this->withdrawFromDepositsTronService->depositsPlan(
                $deposits,
                $merchantMainWallet
            );
        } catch (ModelNotFoundException $e) {
            Log::warning('Withdraw aborted: main wallet not found', [
                'wallet_id' => $walletId,
            ]);

            return WithdrawPlanDto::error('Main wallet not found');
        } catch (Throwable $e) {
            Log::error('Withdraw plan build failed', [
                'wallet_id' => $walletId,
                'message' => $e->getMessage(),
            ]);

            return WithdrawPlanDto::error($e->getMessage());
        }
    }
}

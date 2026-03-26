<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\MainWalletRequest;
use App\Services\Tron\WithdrawDepositExecutionService;
use App\Services\Tron\WithdrawDepositPreparationService;
use Illuminate\Support\Facades\Log;

class WithdrawDepositsController extends Controller
{
    public function __construct(
        private readonly WithdrawDepositPreparationService $withdrawDepositPreparationService,
        private readonly WithdrawDepositExecutionService $withdrawDepositExecutionService,
    ) {}

    public function withdrawDeposits(MainWalletRequest $request)
    {
        $walletId = $request->validated()['wallet_id'];

        Log::info('Withdraw grouped deposits started', [
            'wallet_id' => $walletId,
        ]);

        $plan = $this->withdrawDepositPreparationService->buildWithdrawPlan($walletId);

        if (!$plan->isReady()) {
            Log::warning('Withdraw grouped deposits aborted on preparation stage', [
                'wallet_id' => $walletId,
                'error' => $plan->errorMessage,
                'groups_count' => $plan->groupsCount(),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->withErrors([
                    'balance' => $plan->errorMessage ?? 'Withdraw plan is not ready',
                ]);
        }

        $result = $this->withdrawDepositExecutionService->execute($plan);

        Log::info('Withdraw grouped deposits finished', [
            'wallet_id' => $walletId,
            'processed_groups' => $result->processedGroups,
            'failed_groups' => $result->failedGroups,
            'paid_deposits' => $result->paidDeposits,
            'errors' => $result->errors,
        ]);

        return redirect()
            ->back()
            ->withInput()
            ->withSuccess($result->summary());
    }
}

<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\MainWalletRequest;
use App\Services\Tron\WithdrawDepositPreparationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class WithdrawDepositsController extends Controller
{
    public function __construct(
        private readonly WithdrawDepositPreparationService $withdrawDepositPreparationService,
    ) {
    }

    public function withdrawDeposits(MainWalletRequest $request): RedirectResponse
    {
        $walletId = $request->validated()['wallet_id'];

        Log::info('Withdraw deposits started', [
            'wallet_id' => $walletId,
        ]);

        $plan = $this->withdrawDepositPreparationService->buildWithdrawPlan($walletId);

        if ($plan->hasError()) {
            Log::warning('Withdraw deposits failed', [
                'wallet_id' => $walletId,
                'error' => $plan->errorMessage,
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->withErrors([
                    'withdraw' => $plan->errorMessage ?? 'Ошибка при выполнении выплат.',
                ]);
        }

        if ($plan->isPartial()) {
            Log::warning('Withdraw deposits partially completed', [
                'wallet_id' => $walletId,
                'message' => $plan->message,
                'missing_fee_by_currency' => $plan->missingFeeByCurrency,
                'processed_count' => count($plan->processed),
                'skipped_count' => count($plan->skipped),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('warning', $plan->message)
                ->with('withdraw_result', $plan->toArray());
        }

        Log::info('Withdraw deposits successfully completed', [
            'wallet_id' => $walletId,
            'message' => $plan->message,
            'processed_count' => count($plan->processed),
            'skipped_count' => count($plan->skipped),
        ]);

        return redirect()
            ->back()
            ->with('success', $plan->message)
            ->with('withdraw_result', $plan->toArray());
    }
}

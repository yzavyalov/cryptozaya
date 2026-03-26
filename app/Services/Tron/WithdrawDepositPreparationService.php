<?php

namespace App\Services\Tron;


use App\DTO\Withdraw\WithdrawGroupDto;
use App\DTO\Withdraw\WithdrawPlanDto;
use App\Http\Enums\MerchantTransactionStatusEnum;
use App\Http\Enums\MerchantTypeTransactionEnum;
use App\Models\MerchantWallet;
use App\Services\Operations\CurrencyService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class WithdrawDepositPreparationService
{
    public function __construct(
        private readonly TronService $tronService,
    ) {}

    public function buildWithdrawPlan(int $walletId): WithdrawPlanDto
    {
        $walletMerchant = MerchantWallet::query()->findOrFail($walletId);
        $merchant = $walletMerchant->merchant;
        $merchantMainWallet = $merchant->mainWallet()->first();

        if (!$merchantMainWallet) {
            Log::warning('Withdraw aborted: main wallet not found', [
                'wallet_id' => $walletId,
                'merchant_id' => $merchant->id ?? null,
            ]);

            return new WithdrawPlanDto(
                merchantMainWallet: $walletMerchant,
                errorMessage: 'Main wallet not found',
            );
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
            return new WithdrawPlanDto(
                merchantMainWallet: $merchantMainWallet,
                errorMessage: 'We didn\'t find deposits',
            );
        }

        $groupedDeposits = $this->groupDeposits($deposits);

        $groups = [];
        $totalRequiredFeeByCurrency = [];

        foreach ($groupedDeposits as $groupKey => $walletDeposits) {
            $group = $this->prepareGroup(
                groupKey: $groupKey,
                walletDeposits: $walletDeposits,
                mainWalletAddress: $merchantMainWallet->number,
            );

            if (!$group) {
                continue;
            }

            $groups[] = $group;

            if (!isset($totalRequiredFeeByCurrency[$group->feeCurrency])) {
                $totalRequiredFeeByCurrency[$group->feeCurrency] = '0';
            }

            $totalRequiredFeeByCurrency[$group->feeCurrency] = bcadd(
                $totalRequiredFeeByCurrency[$group->feeCurrency],
                $group->commissionAmount,
                8
            );
        }

        if (empty($groups)) {
            return new WithdrawPlanDto(
                merchantMainWallet: $merchantMainWallet,
                errorMessage: 'No wallets with positive balance were found for withdrawal',
            );
        }

        $missingFeeByCurrency = $this->getMissingFeeByCurrency(
            mainWalletAddress: $merchantMainWallet->number,
            totalRequiredFeeByCurrency: $totalRequiredFeeByCurrency,
        );

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

            return new WithdrawPlanDto(
                merchantMainWallet: $merchantMainWallet,
                groups: $groups,
                totalRequiredFeeByCurrency: $totalRequiredFeeByCurrency,
                missingFeeByCurrency: $missingFeeByCurrency,
                errorMessage: $message,
            );
        }

        Log::info('Withdraw plan prepared successfully', [
            'main_wallet' => $merchantMainWallet->number,
            'groups_count' => count($groups),
            'total_required_fee_by_currency' => $totalRequiredFeeByCurrency,
        ]);

        return new WithdrawPlanDto(
            merchantMainWallet: $merchantMainWallet,
            groups: $groups,
            totalRequiredFeeByCurrency: $totalRequiredFeeByCurrency,
            missingFeeByCurrency: [],
            errorMessage: null,
        );
    }

    private function groupDeposits(Collection $deposits): Collection
    {
        return $deposits->groupBy(function ($deposit) {
            return $deposit->wallet_to . '|' . $deposit->currency_id;
        });
    }

    private function prepareGroup(string $groupKey, Collection $walletDeposits, string $mainWalletAddress): ?WithdrawGroupDto
    {
        try {
            $firstDeposit = $walletDeposits->first();

            if (!$firstDeposit) {
                Log::warning('Grouped deposits has no first deposit', [
                    'group_key' => $groupKey,
                ]);

                return null;
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

                return null;
            }

            $commission = $this->tronService->estimateTRC20Fee(
                $token,
                $walletAddress,
                $mainWalletAddress,
                $walletTokenBalance
            );

            if (!is_array($commission) || !isset($commission['total_fee'], $commission['fee_currency'])) {
                Log::error('Invalid commission response for grouped wallet/token, skipped', [
                    'group_key' => $groupKey,
                    'wallet' => $walletAddress,
                    'token' => $token,
                    'commission' => $commission,
                ]);

                return null;
            }

            return new WithdrawGroupDto(
                groupKey: $groupKey,
                wallet: $walletAddress,
                currencyId: $currencyId,
                token: $token,
                walletBalance: (string) $walletTokenBalance,
                commissionAmount: (string) $commission['total_fee'],
                feeCurrency: (string) $commission['fee_currency'],
                deposits: $walletDeposits,
            );
        } catch (\Throwable $e) {
            Log::error('Failed to prepare grouped wallet/token, skipped', [
                'group_key' => $groupKey,
                'deposit_ids' => $walletDeposits->pluck('id')->toArray(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    private function getMissingFeeByCurrency(string $mainWalletAddress, array $totalRequiredFeeByCurrency): array
    {
        $mainWalletBalances = $this->tronService->getAllBalances($mainWalletAddress);
        $missingFeeByCurrency = [];

        foreach ($totalRequiredFeeByCurrency as $currency => $requiredAmount) {
            $availableAmount = (string) ($mainWalletBalances['balances'][$currency] ?? '0');

            Log::info('Main wallet total fee check', [
                'main_wallet' => $mainWalletAddress,
                'currency' => $currency,
                'required_total_fee' => $requiredAmount,
                'available_total_fee' => $availableAmount,
            ]);

            if (bccomp($availableAmount, $requiredAmount, 8) < 0) {
                $missingFeeByCurrency[$currency] = bcsub($requiredAmount, $availableAmount, 8);
            }
        }

        return $missingFeeByCurrency;
    }
}

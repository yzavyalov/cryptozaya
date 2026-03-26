<?php

namespace App\DTO\Withdraw;

use App\Models\MerchantWallet;

readonly class WithdrawPlanDto
{
    /**
     * @param WithdrawGroupDto[] $groups
     * @param array<string, string> $totalRequiredFeeByCurrency
     * @param array<string, string> $missingFeeByCurrency
     */
    public function __construct(
        public ?MerchantWallet $merchantMainWallet = null,
        public array $groups = [],
        public array $totalRequiredFeeByCurrency = [],
        public array $missingFeeByCurrency = [],
        public ?string $errorMessage = null,
    ) {}

    public function isReady(): bool
    {
        return $this->merchantMainWallet !== null
            && empty($this->errorMessage)
            && !empty($this->groups);
    }

    public function hasErrors(): bool
    {
        return !empty($this->errorMessage);
    }

    public function groupsCount(): int
    {
        return count($this->groups);
    }

    public function totalDeposits(): int
    {
        return array_sum(
            array_map(fn (WithdrawGroupDto $group) => $group->depositsCount(), $this->groups)
        );
    }

    public function missingFeeMessage(): ?string
    {
        if (empty($this->missingFeeByCurrency)) {
            return null;
        }

        $parts = [];

        foreach ($this->missingFeeByCurrency as $currency => $amount) {
            if (bccomp($amount, '0', 8) > 0) {
                $parts[] = "{$amount} {$currency}";
            }
        }

        return implode(', ', $parts);
    }
}

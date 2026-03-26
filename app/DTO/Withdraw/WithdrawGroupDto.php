<?php

namespace App\DTO\Withdraw;

use Illuminate\Support\Collection;

readonly class WithdrawGroupDto
{
    public function __construct(
        public string $groupKey,
        public string $wallet,
        public int $currencyId,
        public string $token,
        public string $walletBalance,
        public string $commissionAmount,
        public string $feeCurrency,
        public Collection $deposits,
    ) {}

    public function depositsCount(): int
    {
        return $this->deposits->count();
    }

    public function depositIds(): array
    {
        return $this->deposits->pluck('id')->toArray();
    }
}

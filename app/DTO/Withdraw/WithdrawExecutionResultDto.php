<?php

namespace App\DTO\Withdraw;

readonly class WithdrawExecutionResultDto
{
    public function __construct(
        public int $processedGroups = 0,
        public int $failedGroups = 0,
        public int $paidDeposits = 0,
        public array $errors = [],
    ) {}

    public function totalGroups(): int
    {
        return $this->processedGroups + $this->failedGroups;
    }

    public function successRate(): float
    {
        $total = $this->totalGroups();

        if ($total === 0) {
            return 0;
        }

        return round(($this->processedGroups / $total) * 100, 2);
    }

    public function hasFailures(): bool
    {
        return $this->failedGroups > 0;
    }

    public function summary(): string
    {
        return "Groups processed: {$this->processedGroups}, groups failed: {$this->failedGroups}, deposits paid: {$this->paidDeposits}";
    }
}

<?php

namespace App\DTO\Withdraw;

use App\Models\MerchantWallet;

class WithdrawPlanDto
{
    public ?MerchantWallet $merchantMainWallet;
    public array $processed;
    public array $skipped;
    public array $totalRequiredFeeByCurrency;
    public array $missingFeeByCurrency;
    public ?string $errorMessage;
    public bool $partial;
    public ?string $message;

    public function __construct(
        ?MerchantWallet $merchantMainWallet = null,
        array $processed = [],
        array $skipped = [],
        array $totalRequiredFeeByCurrency = [],
        array $missingFeeByCurrency = [],
        ?string $errorMessage = null,
        bool $partial = false,
        ?string $message = null,
    ) {
        $this->merchantMainWallet = $merchantMainWallet;
        $this->processed = $processed;
        $this->skipped = $skipped;
        $this->totalRequiredFeeByCurrency = $totalRequiredFeeByCurrency;
        $this->missingFeeByCurrency = $missingFeeByCurrency;
        $this->errorMessage = $errorMessage;
        $this->partial = $partial;
        $this->message = $message;
    }

    public function hasError(): bool
    {
        return $this->errorMessage !== null;
    }

    public function isSuccessful(): bool
    {
        return !$this->hasError() && !$this->partial;
    }

    public function isPartial(): bool
    {
        return $this->partial;
    }

    public static function success(
        ?MerchantWallet $merchantMainWallet = null,
        array $processed = [],
        array $skipped = [],
        array $totalRequiredFeeByCurrency = [],
        ?string $message = 'Все выплаты успешно обработаны.',
    ): self {
        return new self(
            merchantMainWallet: $merchantMainWallet,
            processed: $processed,
            skipped: $skipped,
            totalRequiredFeeByCurrency: $totalRequiredFeeByCurrency,
            missingFeeByCurrency: [],
            errorMessage: null,
            partial: false,
            message: $message,
        );
    }

    public static function partial(
        ?MerchantWallet $merchantMainWallet = null,
        array $processed = [],
        array $skipped = [],
        array $totalRequiredFeeByCurrency = [],
        array $missingFeeByCurrency = [],
        ?string $message = 'Часть выплат обработана. Пополните комиссию и повторите.',
    ): self {
        return new self(
            merchantMainWallet: $merchantMainWallet,
            processed: $processed,
            skipped: $skipped,
            totalRequiredFeeByCurrency: $totalRequiredFeeByCurrency,
            missingFeeByCurrency: $missingFeeByCurrency,
            errorMessage: null,
            partial: true,
            message: $message,
        );
    }

    public static function error(string $message): self
    {
        return new self(
            errorMessage: $message,
            partial: false,
            message: $message,
        );
    }

    public function toArray(): array
    {
        return [
            'status' => $this->hasError()
                ? 'error'
                : ($this->isPartial() ? 'partial' : 'success'),
            'message' => $this->message,
            'error_message' => $this->errorMessage,
            'merchant_main_wallet' => $this->merchantMainWallet?->number,
            'processed' => $this->processed,
            'skipped' => $this->skipped,
            'total_required_fee_by_currency' => $this->totalRequiredFeeByCurrency,
            'missing_fee_by_currency' => $this->missingFeeByCurrency,
        ];
    }
}

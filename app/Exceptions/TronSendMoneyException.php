<?php

namespace App\Exceptions;

use Exception;

class TronSendMoneyException extends Exception
{
    protected string $codeName;

    public function __construct(string $message, string $codeName, int $code = 0, ?Throwable $previous = null)
    {
        $this->codeName = $codeName;

        parent::__construct($message, $code, $previous);
    }

    public function getCodeName(): string
    {
        return $this->codeName;
    }
}

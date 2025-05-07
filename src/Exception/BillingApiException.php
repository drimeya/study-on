<?php

namespace App\Exception;

class BillingApiException extends \Exception
{
    private int $statusCode;

    public function __construct(string $message = '', int $statusCode = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}

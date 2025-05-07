<?php

namespace App\Dto;

class BillingResponse
{
    public function __construct(
        public readonly int $code,
        public readonly array $data,
        public readonly ?string $message = null
    ) {
    }

    public static function fromArray(array $response): self
    {
        return new self(
            code: $response['code'] ?? 0,
            data: $response['data'] ?? [],
            message: $response['data']['message'] ?? null
        );
    }

    public function isSuccess(): bool
    {
        return $this->code >= 200 && $this->code < 300;
    }

    public function isCreated(): bool
    {
        return $this->code === 201;
    }
}

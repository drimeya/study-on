<?php

namespace App\Dto;

class BillingTransactionResponse
{
    public function __construct(
        public readonly int $id,
        public readonly string $createdAt,
        public readonly string $type,
        public readonly float $amount,
        public readonly ?string $courseCode = null,
        public readonly ?string $expiresAt = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? 0,
            createdAt: $data['created_at'] ?? '',
            type: $data['type'] ?? '',
            amount: (float) ($data['amount'] ?? 0),
            courseCode: $data['course_code'] ?? null,
            expiresAt: $data['expires_at'] ?? null
        );
    }

    public function isPayment(): bool
    {
        return $this->type === 'payment';
    }

    public function isDeposit(): bool
    {
        return $this->type === 'deposit';
    }

    public function getExpiresAtDateTime(): ?\DateTimeImmutable
    {
        if ($this->expiresAt === null) {
            return null;
        }
        try {
            return new \DateTimeImmutable($this->expiresAt);
        } catch (\Exception) {
            return null;
        }
    }
}

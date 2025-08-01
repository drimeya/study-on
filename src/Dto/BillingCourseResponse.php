<?php

namespace App\Dto;

class BillingCourseResponse
{
    public function __construct(
        public readonly string $code,
        public readonly string $type,
        public readonly ?float $price = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            code: $data['code'] ?? '',
            type: $data['type'] ?? 'free',
            price: isset($data['price']) ? (float) $data['price'] : null
        );
    }

    public function isFree(): bool
    {
        return $this->type === 'free';
    }

    public function isRent(): bool
    {
        return $this->type === 'rent';
    }

    public function isBuy(): bool
    {
        return $this->type === 'buy';
    }

    public function getTypeLabel(): string
    {
        return match($this->type) {
            'rent' => 'Аренда',
            'buy'  => 'Покупка',
            default => 'Бесплатно',
        };
    }
}

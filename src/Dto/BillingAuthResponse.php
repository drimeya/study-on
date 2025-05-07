<?php

namespace App\Dto;

class BillingAuthResponse
{
    public function __construct(
        public readonly string $token,
        public readonly array $roles,
        public readonly string $username,
        public readonly int $balance = 0
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            token: $data['token'] ?? '',
            roles: $data['roles'] ?? ['ROLE_USER'],
            username: $data['username'] ?? '',
            balance: $data['balance'] ?? 0
        );
    }
}

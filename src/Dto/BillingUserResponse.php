<?php

namespace App\Dto;

class BillingUserResponse
{
    public function __construct(
        public readonly string $username,
        public readonly array $roles,
        public readonly int $balance,
        public readonly string $email = ''
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            username: $data['username'] ?? '',
            roles: $data['roles'] ?? ['ROLE_USER'],
            balance: $data['balance'] ?? 0,
            email: $data['email'] ?? $data['username'] ?? ''
        );
    }
}

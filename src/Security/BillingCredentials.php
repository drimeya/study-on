<?php

namespace App\Security;

use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CredentialsInterface;

class BillingCredentials implements CredentialsInterface
{
    public function __construct(
        private string $email,
        private string $password
    ) {
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function isResolved(): bool
    {
        // Аутентификация уже проверена в BillingAuthenticator через API
        return true;
    }
}

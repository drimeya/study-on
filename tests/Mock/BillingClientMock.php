<?php

namespace App\Tests\Mock;

use App\Dto\BillingAuthResponse;
use App\Dto\BillingUserResponse;
use App\Exception\BillingApiException;
use App\Exception\BillingUnavailableException;
use App\Service\BillingClient;

class BillingClientMock extends BillingClient
{
    private array $users = [];
    private array $tokens = [];

    public function __construct(string $billingUrl)
    {
        parent::__construct($billingUrl);
        
        // Инициализируем тестовых пользователей
        $this->users = [
            'test@example.com' => [
                'password' => 'password123',
                'roles' => ['ROLE_USER'],
                'balance' => 1000
            ],
            'admin@example.com' => [
                'password' => 'admin123',
                'roles' => ['ROLE_SUPER_ADMIN'],
                'balance' => 5000
            ]
        ];
    }

    public function auth(string $email, string $password): BillingAuthResponse
    {
        if (!isset($this->users[$email])) {
            throw new BillingApiException('Unauthorized: User not found', 401);
        }

        $user = $this->users[$email];
        if ($user['password'] !== $password) {
            throw new BillingApiException('Unauthorized: Invalid password', 401);
        }

        $token = 'mock_token_' . md5($email . time());
        $this->tokens[$token] = $email;

        return new BillingAuthResponse(
            token: $token,
            roles: $user['roles'],
            username: $email
        );
    }

    public function register(string $email, string $password): BillingAuthResponse
    {
        if (isset($this->users[$email])) {
            throw new BillingApiException('Unauthorized: User with this email already exists', 401);
        }

        if (strlen($password) < 6) {
            throw new BillingApiException('Validation error: Password too short', 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BillingApiException('Validation error: Invalid email format', 422);
        }

        // Регистрируем нового пользователя
        $this->users[$email] = [
            'password' => $password,
            'roles' => ['ROLE_USER'],
            'balance' => 0
        ];

        $token = 'mock_token_' . md5($email . time());
        $this->tokens[$token] = $email;

        return new BillingAuthResponse(
            token: $token,
            roles: ['ROLE_USER'],
            username: $email
        );
    }

    public function getCurrentUser(string $token): BillingUserResponse
    {
        if (!isset($this->tokens[$token])) {
            throw new BillingApiException('Unauthorized: Invalid token', 401);
        }

        $email = $this->tokens[$token];
        if (!isset($this->users[$email])) {
            throw new BillingApiException('Unauthorized: User not found', 401);
        }

        $user = $this->users[$email];

        return new BillingUserResponse(
            username: $email,
            roles: $user['roles'],
            balance: $user['balance']
        );
    }

    public function request(string $endpoint, array $data = [], string $method = 'GET', ?string $token = null): \App\Dto\BillingResponse
    {
        // Для других методов возвращаем базовую реализацию
        return parent::request($endpoint, $data, $method, $token);
    }

    /**
     * Методы для тестирования - позволяют управлять состоянием mock'а
     */
    public function addUser(string $email, string $password, array $roles = ['ROLE_USER'], int $balance = 0): void
    {
        $this->users[$email] = [
            'password' => $password,
            'roles' => $roles,
            'balance' => $balance
        ];
    }

    public function removeUser(string $email): void
    {
        unset($this->users[$email]);
    }

    public function getUserCount(): int
    {
        return count($this->users);
    }

    public function hasUser(string $email): bool
    {
        return isset($this->users[$email]);
    }
}

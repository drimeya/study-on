<?php

namespace App\Tests\Mock;

use App\Dto\BillingAuthResponse;
use App\Dto\BillingCourseResponse;
use App\Dto\BillingResponse;
use App\Dto\BillingTransactionResponse;
use App\Dto\BillingUserResponse;
use App\Exception\BillingApiException;
use App\Service\BillingClient;

class BillingClientMock extends BillingClient
{
    private array $users = [];
    private array $tokens = [];
    private array $refreshTokens = [];

    /** course_code => ['code', 'type', 'price'] */
    private array $courses = [];

    /** email => [['id', 'type', 'amount', 'course_code', 'created_at', 'expires_at']] */
    private array $transactions = [];

    private int $transactionIdSeq = 1;

    public function __construct(string $billingUrl)
    {
        parent::__construct($billingUrl);

        $this->users = [
            'test@example.com' => [
                'password' => 'password123',
                'roles' => ['ROLE_USER'],
                'balance' => 1000.0,
            ],
            'admin@example.com' => [
                'password' => 'admin123',
                'roles' => ['ROLE_SUPER_ADMIN'],
                'balance' => 5000.0,
            ],
        ];

        // Начальные депозиты для встроенных пользователей
        $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        $this->transactions = [
            'test@example.com' => [
                ['id' => $this->transactionIdSeq++, 'type' => 'deposit', 'amount' => 1000.0, 'created_at' => $now, 'course_code' => null, 'expires_at' => null],
            ],
            'admin@example.com' => [
                ['id' => $this->transactionIdSeq++, 'type' => 'deposit', 'amount' => 5000.0, 'created_at' => $now, 'course_code' => null, 'expires_at' => null],
            ],
        ];

        // Курсы соответствуют кодам из CourseFixtures обоих сервисов (course-0..course-4)
        $this->courses = [
            'course-0' => ['code' => 'course-0', 'title' => 'Изучение Symfony',  'type' => 'free'],
            'course-1' => ['code' => 'course-1', 'title' => 'Doctrine ORM',       'type' => 'rent', 'price' => 99.90],
            'course-2' => ['code' => 'course-2', 'title' => 'Модель данных',      'type' => 'buy',  'price' => 159.00],
            'course-3' => ['code' => 'course-3', 'title' => 'Frontend в Symfony', 'type' => 'free'],
            'course-4' => ['code' => 'course-4', 'title' => 'Тестирование',       'type' => 'rent', 'price' => 149.90],
        ];
    }

    // -------------------------------------------------------------------------
    // Auth
    // -------------------------------------------------------------------------

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
        $refreshToken = 'mock_refresh_' . md5($email . time() . 'refresh');
        $this->tokens[$token] = $email;
        $this->refreshTokens[$refreshToken] = $email;

        return new BillingAuthResponse(
            token: $token,
            roles: $user['roles'],
            username: $email,
            refreshToken: $refreshToken
        );
    }

    public function register(string $email, string $password): BillingAuthResponse
    {
        if (isset($this->users[$email])) {
            throw new BillingApiException('Conflict: User with this email already exists', 409);
        }

        if (strlen($password) < 6) {
            throw new BillingApiException('Validation error: Password too short', 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BillingApiException('Validation error: Invalid email format', 422);
        }

        $this->users[$email] = [
            'password' => $password,
            'roles' => ['ROLE_USER'],
            'balance' => 1000.0, // начальный депозит
        ];

        $token = 'mock_token_' . md5($email . time());
        $refreshToken = 'mock_refresh_' . md5($email . time() . 'refresh');
        $this->tokens[$token] = $email;
        $this->refreshTokens[$refreshToken] = $email;

        // Записываем депозит в историю транзакций
        $this->addTransaction($email, [
            'type' => 'deposit',
            'amount' => 1000.0,
        ]);

        return new BillingAuthResponse(
            token: $token,
            roles: ['ROLE_USER'],
            username: $email,
            refreshToken: $refreshToken
        );
    }

    public function getCurrentUser(string $token): BillingUserResponse
    {
        $email = $this->resolveEmail($token);
        $user = $this->users[$email];

        return new BillingUserResponse(
            username: $email,
            roles: $user['roles'],
            balance: $user['balance']
        );
    }

    public function refreshToken(string $refreshToken): BillingAuthResponse
    {
        if (!isset($this->refreshTokens[$refreshToken])) {
            throw new BillingApiException('Unauthorized: Invalid refresh token', 401);
        }

        $email = $this->refreshTokens[$refreshToken];
        if (!isset($this->users[$email])) {
            throw new BillingApiException('Unauthorized: User not found', 401);
        }

        unset($this->refreshTokens[$refreshToken]);

        $newToken = 'mock_token_' . md5($email . time() . 'new');
        $newRefreshToken = 'mock_refresh_' . md5($email . time() . 'new_refresh');
        $this->tokens[$newToken] = $email;
        $this->refreshTokens[$newRefreshToken] = $email;

        $user = $this->users[$email];

        return new BillingAuthResponse(
            token: $newToken,
            roles: $user['roles'],
            username: $email,
            refreshToken: $newRefreshToken
        );
    }

    // -------------------------------------------------------------------------
    // Courses
    // -------------------------------------------------------------------------

    public function getCourses(?string $token = null): array
    {
        return array_map(
            fn (array $c) => BillingCourseResponse::fromArray($c),
            array_values($this->courses)
        );
    }

    public function getCourse(string $code, ?string $token = null): ?BillingCourseResponse
    {
        if (!isset($this->courses[$code])) {
            return null;
        }

        return BillingCourseResponse::fromArray($this->courses[$code]);
    }

    public function payCourse(string $code, string $token): array
    {
        $email = $this->resolveEmail($token);

        if (!isset($this->courses[$code])) {
            throw new BillingApiException('Not found: Курс не найден', 404);
        }

        $course = $this->courses[$code];

        if ($course['type'] === 'free') {
            return ['success' => true, 'course_type' => 'free', 'expires_at' => null];
        }

        $price = $course['price'] ?? 0.0;
        if ($this->users[$email]['balance'] < $price) {
            throw new BillingApiException('На вашем счету недостаточно средств', 406);
        }

        $this->users[$email]['balance'] -= $price;

        $expiresAt = null;
        if ($course['type'] === 'rent') {
            $expiresAt = (new \DateTimeImmutable('+1 week'))->format(\DateTimeInterface::ATOM);
        }

        $this->addTransaction($email, [
            'type' => 'payment',
            'amount' => $price,
            'course_code' => $code,
            'expires_at' => $expiresAt,
        ]);

        return [
            'success' => true,
            'course_type' => $course['type'],
            'expires_at' => $expiresAt,
        ];
    }

    public function createCourse(string $token, string $code, string $title, string $type, ?float $price = null): void
    {
        $this->resolveEmail($token); // проверяем токен

        if (isset($this->courses[$code])) {
            throw new BillingApiException('Validation error: Курс с таким кодом уже существует', 422);
        }

        $allowedTypes = ['free', 'rent', 'buy'];
        if (!in_array($type, $allowedTypes, true)) {
            throw new BillingApiException('Validation error: Недопустимый тип курса', 422);
        }

        $this->courses[$code] = array_filter([
            'code'  => $code,
            'title' => $title,
            'type'  => $type,
            'price' => ($type === 'free') ? null : $price,
        ], fn ($v) => $v !== null);
    }

    public function updateCourse(string $token, string $currentCode, string $newCode, string $title, string $type, ?float $price = null): void
    {
        $this->resolveEmail($token); // проверяем токен

        if (!isset($this->courses[$currentCode])) {
            throw new BillingApiException('Not found: Курс не найден', 404);
        }

        $allowedTypes = ['free', 'rent', 'buy'];
        if (!in_array($type, $allowedTypes, true)) {
            throw new BillingApiException('Validation error: Недопустимый тип курса', 422);
        }

        if ($newCode !== $currentCode && isset($this->courses[$newCode])) {
            throw new BillingApiException('Validation error: Курс с таким кодом уже существует', 422);
        }

        $updated = array_filter([
            'code'  => $newCode,
            'title' => $title,
            'type'  => $type,
            'price' => ($type === 'free') ? null : $price,
        ], fn ($v) => $v !== null);

        unset($this->courses[$currentCode]);
        $this->courses[$newCode] = $updated;
    }

    // -------------------------------------------------------------------------
    // Transactions
    // -------------------------------------------------------------------------

    public function getTransactions(string $token, array $filters = []): array
    {
        $email = $this->resolveEmail($token);
        $all = $this->transactions[$email] ?? [];

        // Фильтр по типу
        if (!empty($filters['type'])) {
            $all = array_filter($all, fn ($t) => $t['type'] === $filters['type']);
        }

        // Фильтр по коду курса
        if (!empty($filters['course_code'])) {
            $all = array_filter($all, fn ($t) => ($t['course_code'] ?? null) === $filters['course_code']);
        }

        // Пропуск истёкших арендных транзакций
        if (!empty($filters['skip_expired'])) {
            $now = new \DateTimeImmutable();
            $all = array_filter($all, function ($t) use ($now) {
                if (empty($t['expires_at'])) {
                    return true;
                }
                try {
                    return new \DateTimeImmutable($t['expires_at']) > $now;
                } catch (\Exception) {
                    return true;
                }
            });
        }

        return array_map(
            fn (array $t) => BillingTransactionResponse::fromArray($t),
            array_values($all)
        );
    }

    // -------------------------------------------------------------------------
    // Passthrough (не вызывается в тестах, но нужен для совместимости)
    // -------------------------------------------------------------------------

    public function request(string $endpoint, array $data = [], string $method = 'GET', ?string $token = null, array $queryParams = []): BillingResponse
    {
        return parent::request($endpoint, $data, $method, $token, $queryParams);
    }

    // -------------------------------------------------------------------------
    // Вспомогательные методы для тестов
    // -------------------------------------------------------------------------

    public function addUser(string $email, string $password, array $roles = ['ROLE_USER'], float $balance = 0.0): void
    {
        $this->users[$email] = [
            'password' => $password,
            'roles' => $roles,
            'balance' => $balance,
        ];
    }

    public function addCourse(string $code, string $type, ?float $price = null, ?string $title = null): void
    {
        $this->courses[$code] = array_filter([
            'code'  => $code,
            'title' => $title ?? $code,
            'type'  => $type,
            'price' => $price,
        ], fn ($v) => $v !== null);
    }

    public function setUserBalance(string $email, float $balance): void
    {
        if (isset($this->users[$email])) {
            $this->users[$email]['balance'] = $balance;
        }
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

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function resolveEmail(string $token): string
    {
        if (!isset($this->tokens[$token])) {
            throw new BillingApiException('Unauthorized: Invalid token', 401);
        }

        $email = $this->tokens[$token];

        if (!isset($this->users[$email])) {
            throw new BillingApiException('Unauthorized: User not found', 401);
        }

        return $email;
    }

    private function addTransaction(string $email, array $data): void
    {
        $this->transactions[$email][] = array_merge([
            'id' => $this->transactionIdSeq++,
            'created_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'course_code' => null,
            'expires_at' => null,
        ], $data);
    }
}

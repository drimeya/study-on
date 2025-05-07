<?php

namespace App\Service\RateLimiter;

class LoginRateLimiter extends AbstractRateLimiter
{
    private const LOGIN_ATTEMPTS_LIMIT = 5;
    private const LOGIN_ATTEMPTS_WINDOW = 900; // 15 минут
    private const KEY_PREFIX = 'login_attempts';

    /**
     * Проверяет лимит попыток входа
     */
    public function checkLimit(string $email): bool
    {
        $key = $this->generateKey(self::KEY_PREFIX, $email);
        return $this->checkRateLimit($key, self::LOGIN_ATTEMPTS_LIMIT, self::LOGIN_ATTEMPTS_WINDOW);
    }

    /**
     * Сбрасывает счетчик попыток входа
     */
    public function resetAttempts(string $email): void
    {
        $key = $this->generateKey(self::KEY_PREFIX, $email);
        parent::resetAttempts($key);
    }
}

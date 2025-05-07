<?php

namespace App\Service\RateLimiter;

class RegistrationRateLimiter extends AbstractRateLimiter
{
    private const REGISTRATION_LIMIT = 3;
    private const REGISTRATION_WINDOW = 3600; // 1 час
    private const KEY_PREFIX = 'registration_attempts';

    /**
     * Проверяет лимит регистраций
     */
    public function checkLimit(string $ip): bool
    {
        $key = $this->generateKey(self::KEY_PREFIX, $ip);
        return $this->checkRateLimit($key, self::REGISTRATION_LIMIT, self::REGISTRATION_WINDOW);
    }
}

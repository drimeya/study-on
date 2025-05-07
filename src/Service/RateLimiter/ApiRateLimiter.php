<?php

namespace App\Service\RateLimiter;

class ApiRateLimiter extends AbstractRateLimiter
{
    private const API_CALLS_LIMIT = 100;
    private const API_CALLS_WINDOW = 3600; // 1 час
    private const KEY_PREFIX = 'api_calls';

    /**
     * Проверяет лимит API вызовов
     */
    public function checkLimit(string $token): bool
    {
        $key = $this->generateKey(self::KEY_PREFIX, $token);
        return $this->checkRateLimit($key, self::API_CALLS_LIMIT, self::API_CALLS_WINDOW);
    }
}

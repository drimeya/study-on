<?php

namespace App\Service\RateLimiter;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

abstract class AbstractRateLimiter
{
    public function __construct(
        protected CacheInterface $cache
    ) {
    }

    /**
     * Проверяет rate limit
     */
    protected function checkRateLimit(string $key, int $limit, int $window): bool
    {
        $attempts = $this->cache->get($key, function (ItemInterface $item) use ($window) {
            $item->expiresAfter($window);
            return 0;
        });

        if ($attempts >= $limit) {
            return false;
        }

        $this->incrementAttempts($key, $window);
        return true;
    }

    /**
     * Увеличивает счетчик попыток
     */
    protected function incrementAttempts(string $key, int $window): void
    {
        $this->cache->get($key, function (ItemInterface $item) use ($window) {
            $item->expiresAfter($window);
            $currentAttempts = $item->get() ?? 0;
            return $currentAttempts + 1;
        });
    }

    /**
     * Сбрасывает счетчик попыток
     */
    protected function resetAttempts(string $key): void
    {
        $this->cache->delete($key);
    }

    /**
     * Генерирует ключ для кэша
     */
    protected function generateKey(string $prefix, string $identifier): string
    {
        return $prefix . '_' . md5($identifier);
    }
}

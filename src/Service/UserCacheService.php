<?php

namespace App\Service;

use App\Exception\BillingUnavailableException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class UserCacheService
{
    private const CACHE_TTL = 300; // 5 минут
    private const TOKEN_CACHE_PREFIX = 'token_';

    public function __construct(
        private BillingClient $billingClient,
        private CacheInterface $cache
    ) {
    }

    /**
     * Получает кэшированные данные пользователя по токену
     */
    public function getCachedUserData(string $token): ?array
    {
        return $this->cache->get(
            $this->generateTokenKey($token),
            function (ItemInterface $item) use ($token) {
                $item->expiresAfter(self::CACHE_TTL);
                
                try {
                    $response = $this->billingClient->getCurrentUser($token);
                    return [
                        'roles' => $response->roles,
                        'username' => $response->username,
                        'balance' => $response->balance,
                        'timestamp' => time()
                    ];
                } catch (BillingUnavailableException $e) {
                    // Если API недоступен, возвращаем null
                    return null;
                }
            }
        );
    }

    /**
     * Инвалидирует кэш для токена
     */
    public function invalidateTokenCache(string $token): void
    {
        $this->cache->delete($this->generateTokenKey($token));
    }

    /**
     * Генерирует ключ для кэша токена
     */
    private function generateTokenKey(string $token): string
    {
        return self::TOKEN_CACHE_PREFIX . md5($token);
    }
}

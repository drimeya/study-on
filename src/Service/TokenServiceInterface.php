<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\UserApiToken;

interface TokenServiceInterface
{
    /**
     * Создает токен для пользователя
     */
    public function createToken(User $user, string $token, ?\DateTimeImmutable $expiresAt = null, bool $secure = true): UserApiToken;

    /**
     * Создает безопасный токен (с хешированием)
     */
    public function createSecureToken(User $user, string $externalToken, ?\DateTimeImmutable $expiresAt = null): UserApiToken;

    /**
     * Проверяет валидность токена
     */
    public function validateToken(User $user, string $token): bool;

    /**
     * Получает активный токен пользователя
     */
    public function getActiveToken(User $user): ?UserApiToken;

    /**
     * Проверяет, есть ли у пользователя активный токен
     */
    public function hasActiveToken(User $user): bool;

    /**
     * Деактивирует все токены пользователя
     */
    public function deactivateAllTokens(User $user): void;

    /**
     * Очищает истекшие токены
     */
    public function cleanupExpiredTokens(): int;
}

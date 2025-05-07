<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\UserApiToken;
use App\Repository\UserApiTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class TokenService implements TokenServiceInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserApiTokenRepository $userApiTokenRepository,
        private string $maxTokensPerUser,
        private string $defaultExpirationHours
    ) {
    }

    /**
     * Создает токен для пользователя
     */
    public function createToken(User $user, string $token, ?\DateTimeImmutable $expiresAt = null, bool $secure = true): UserApiToken
    {
        // Проверяем лимит токенов
        $this->enforceTokenLimit($user);
        
        // Деактивируем старые токены
        $this->deactivateAllTokens($user);
        
        // Создаем новый токен
        $apiToken = new UserApiToken();
        $apiToken->setUser($user);
        $apiToken->setToken($secure ? $this->hashToken($token) : $token);
        $apiToken->setExpiresAt($expiresAt ?? $this->getDefaultExpiration());
        $apiToken->setIsActive(true);
        
        $this->entityManager->persist($apiToken);
        $this->entityManager->flush();
        
        return $apiToken;
    }

    /**
     * Создает безопасный токен (с хешированием)
     */
    public function createSecureToken(User $user, string $externalToken, ?\DateTimeImmutable $expiresAt = null): UserApiToken
    {
        return $this->createToken($user, $externalToken, $expiresAt, true);
    }

    /**
     * Проверяет валидность токена
     */
    public function validateToken(User $user, string $token): bool
    {
        $apiToken = $this->getActiveToken($user);
        
        if (!$apiToken) {
            return false;
        }

        return hash_equals($apiToken->getToken(), $this->hashToken($token)) && $apiToken->isValid();
    }

    /**
     * Получает активный токен пользователя
     */
    public function getActiveToken(User $user): ?UserApiToken
    {
        return $this->userApiTokenRepository->findActiveTokenByUser($user->getId());
    }

    /**
     * Получает токен в виде строки (только для внутреннего использования)
     */
    public function getTokenString(User $user): ?string
    {
        $token = $this->getActiveToken($user);
        return $token?->getToken();
    }

    /**
     * Проверяет, есть ли у пользователя активный токен
     */
    public function hasActiveToken(User $user): bool
    {
        return $this->getActiveToken($user) !== null;
    }

    /**
     * Деактивирует все токены пользователя
     */
    public function deactivateAllTokens(User $user): void
    {
        $userId = $user->getId();
        if ($userId === null) {
            return; // Пользователь еще не сохранен в базе данных
        }
        $this->userApiTokenRepository->deactivateAllTokensByUser($userId);
    }

    /**
     * Инвалидирует все токены пользователя (алиас для совместимости)
     */
    public function invalidateAllTokens(User $user): void
    {
        $this->deactivateAllTokens($user);
    }

    /**
     * Очищает истекшие токены
     */
    public function cleanupExpiredTokens(): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->delete('App\Entity\UserApiToken', 't')
           ->where('t.expiresAt < :now')
           ->andWhere('t.isActive = :active')
           ->setParameter('now', new \DateTimeImmutable())
           ->setParameter('active', true);

        return $qb->getQuery()->execute();
    }

    /**
     * Хеширует токен для безопасного хранения
     */
    private function hashToken(string $token): string
    {
        return hash('sha256', $token . ($_ENV['APP_SECRET'] ?? 'default_secret'));
    }

    /**
     * Проверяет лимит токенов на пользователя
     */
    private function enforceTokenLimit(User $user): void
    {
        $activeTokensCount = $this->userApiTokenRepository->count([
            'user' => $user,
            'isActive' => true
        ]);

        if ($activeTokensCount >= (int)$this->maxTokensPerUser) {
            throw new AuthenticationException('Превышен лимит активных токенов');
        }
    }

    /**
     * Возвращает время истечения по умолчанию
     */
    private function getDefaultExpiration(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable())->modify('+' . $this->defaultExpirationHours . ' hours');
    }
}

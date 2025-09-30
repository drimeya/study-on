<?php

namespace App\Security;

use App\Entity\User;
use App\Entity\UserApiToken;
use App\Exception\BillingUnavailableException;
use App\Repository\UserRepository;
use App\Repository\UserApiTokenRepository;
use App\Service\BillingClient;
use App\Service\JwtService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface
{
    public function __construct(
        private BillingClient $billingClient,
        private RequestStack $requestStack,
        private UserRepository $userRepository,
        private UserApiTokenRepository $userApiTokenRepository,
        private JwtService $jwtService,
        private LoggerInterface $logger
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->userRepository->findOneBy(['email' => $identifier]);

        if (!$user) {
            $user = new User();
            $user->setEmail($identifier);
            $user->setPassword('');
        }

        $session = $this->requestStack->getSession();
        $token = $session?->get('billing_token');
        $roles = $session?->get('billing_roles', ['ROLE_USER']);

        if ($token) {
            $this->createOrUpdateApiToken($user, $token);

            try {
                $userResponse = $this->billingClient->getCurrentUser($token);

                if (!empty($userResponse->roles)) {
                    $roles = $userResponse->roles;
                }

                if (!empty($userResponse->username)) {
                    $user->setEmail($userResponse->username);
                }
            } catch (BillingUnavailableException $e) {
                // Если API недоступен, используем роли из сессии
            }
        }

        $user->setRoles($roles);

        $this->userRepository->save($user, true);

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        /** @var User $user */
        $session = $this->requestStack->getSession();
        $currentToken = $session?->get('billing_token');

        // Пытаемся расшифровать токен. Если он не является валидным JWT (например, mock-токен
        // в тестах или legacy-токен), decode() вернёт null — обновление не нужно.
        $payload = $currentToken !== null ? $this->jwtService->decode($currentToken) : null;

        if ($payload !== null && $this->jwtService->isExpired($currentToken)) {
            // Токен истёк (или скоро истечёт) — пробуем обновить через refresh_token
            $refreshToken = $user->getRefreshToken()
                ?? $session?->get('billing_refresh_token');

            if ($refreshToken !== null) {
                try {
                    $authResponse = $this->billingClient->refreshToken($refreshToken);

                    // Обновляем сессию
                    $session->set('billing_token', $authResponse->token);
                    $session->set('billing_roles', $authResponse->roles);
                    if ($authResponse->refreshToken !== null) {
                        $session->set('billing_refresh_token', $authResponse->refreshToken);
                    }

                    // Обновляем refresh_token в сущности пользователя
                    $user->setRefreshToken($authResponse->refreshToken ?? $refreshToken);
                    $this->userRepository->save($user, true);

                    $this->logger->info('JWT-токен обновлён для пользователя', [
                        'email' => $user->getUserIdentifier(),
                    ]);
                } catch (\Throwable $e) {
                    // Если обновление не удалось — продолжаем с текущим токеном;
                    // пользователь будет разлогинен на следующем запросе к биллингу
                    $this->logger->warning('Не удалось обновить JWT-токен', [
                        'email' => $user->getUserIdentifier(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }

    /**
     * Создает или обновляет API токен для пользователя
     */
    private function createOrUpdateApiToken(User $user, string $token): void
    {
        if (!$user->getId()) {
            $this->userRepository->save($user, true);
        }

        $existingToken = $this->userApiTokenRepository->findActiveTokenByUser($user->getId());

        if ($existingToken) {
            $existingToken->setToken($token);
            $existingToken->setExpiresAt(new \DateTimeImmutable('+1 hour'));
            $this->userApiTokenRepository->save($existingToken, true);
        } else {
            $apiToken = new UserApiToken();
            $apiToken->setUser($user);
            $apiToken->setToken($token);
            $apiToken->setExpiresAt(new \DateTimeImmutable('+1 hour'));
            $this->userApiTokenRepository->save($apiToken, true);
        }
    }
}

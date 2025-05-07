<?php

namespace App\Security;

use App\Entity\User;
use App\Entity\UserApiToken;
use App\Exception\BillingUnavailableException;
use App\Repository\UserRepository;
use App\Repository\UserApiTokenRepository;
use App\Service\BillingClient;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface
{
    public function __construct(
        private BillingClient $billingClient,
        private RequestStack $requestStack,
        private UserRepository $userRepository,
        private UserApiTokenRepository $userApiTokenRepository
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // Сначала пытаемся найти пользователя в базе данных
        $user = $this->userRepository->findOneBy(['email' => $identifier]);
        
        if (!$user) {
            // Если пользователя нет в базе, создаем нового
            $user = new User();
            $user->setEmail($identifier);
            // Устанавливаем пустой пароль, так как аутентификация происходит через внешний сервис
            $user->setPassword('');
        }
        
        $session = $this->requestStack->getSession();
        $token = $session?->get('billing_token');
        $roles = $session?->get('billing_roles', ['ROLE_USER']);
        
        if ($token) {
            // Создаем или обновляем API токен для пользователя
            $this->createOrUpdateApiToken($user, $token);
            
            try {
                $userResponse = $this->billingClient->getCurrentUser($token);
                
                // Получаем роли из объекта BillingUserResponse
                if (!empty($userResponse->roles)) {
                    $roles = $userResponse->roles;
                }
                
                // Обновляем email из ответа API
                if (!empty($userResponse->username)) {
                    $user->setEmail($userResponse->username);
                }
            } catch (BillingUnavailableException $e) {
                // Если API недоступен, используем роли из сессии
            }
        }
        
        $user->setRoles($roles);
        
        // Сохраняем или обновляем пользователя в базе данных
        $this->userRepository->save($user, true);
        
        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
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
        // Сначала сохраняем пользователя, чтобы получить ID
        if (!$user->getId()) {
            $this->userRepository->save($user, true);
        }

        // Ищем активный токен для пользователя
        $existingToken = $this->userApiTokenRepository->findActiveTokenByUser($user->getId());
        
        if ($existingToken) {
            // Обновляем существующий токен
            $existingToken->setToken($token);
            $existingToken->setExpiresAt(new \DateTimeImmutable('+1 hour')); // Токен действует 1 час
            $this->userApiTokenRepository->save($existingToken, true);
        } else {
            // Создаем новый токен
            $apiToken = new UserApiToken();
            $apiToken->setUser($user);
            $apiToken->setToken($token);
            $apiToken->setExpiresAt(new \DateTimeImmutable('+1 hour')); // Токен действует 1 час
            $this->userApiTokenRepository->save($apiToken, true);
        }
    }
} 
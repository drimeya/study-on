<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class UserService
{
    public function __construct(
        private UserRepository $userRepository,
        private UserProviderInterface $userProvider,
        private TokenServiceInterface $tokenService,
        private UserCacheService $userCacheService
    ) {
    }

    /**
     * Создает или обновляет пользователя с данными из сессии
     */
    public function createOrUpdateUserFromSession(string $email, SessionInterface $session): User
    {
        // Сначала пытаемся найти пользователя в базе данных
        $user = $this->userRepository->findOneBy(['email' => $email]);
        
        $isNewUser = false;

        if (!$user) {
            // Если пользователя нет в базе, создаем нового
            $user = new User();
            $user->setEmail($email);
            $user->setPassword('');
            $isNewUser = true;
        }
        
        $token = $session->get('billing_token');
        $roles = $session->get('billing_roles', ['ROLE_USER']);
        $refreshToken = $session->get('billing_refresh_token');

        // Новый пользователь должен быть сохранен до создания токена,
        // иначе Doctrine считает его "новым" при сохранении UserApiToken
        if ($isNewUser) {
            $this->userRepository->save($user, true);
        }

        if ($token) {
            // Сохраняем JWT access-токен в базе данных
            $this->tokenService->createToken($user, $token, null, false);

            // Сохраняем refresh-токен в сущности пользователя
            if ($refreshToken !== null) {
                $user->setRefreshToken($refreshToken);
            }

            // Получаем кэшированные данные пользователя
            $cachedUserData = $this->userCacheService->getCachedUserData($token);

            if ($cachedUserData) {
                $user->setRoles($cachedUserData['roles']);
                $user->setEmail($cachedUserData['username']);
            } else {
                // Если кэш пустой, используем роли из сессии
                $user->setRoles($roles);
            }
        } else {
            $user->setRoles($roles);
        }
        
        // Сохраняем или обновляем пользователя в базе данных
        $this->userRepository->save($user, true);
        
        return $user;
    }
}

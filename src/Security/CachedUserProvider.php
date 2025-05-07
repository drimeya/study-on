<?php

namespace App\Security;

use App\Entity\User;
use App\Service\UserService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class CachedUserProvider implements UserProviderInterface
{
    public function __construct(
        private RequestStack $requestStack,
        private UserService $userService
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $session = $this->requestStack->getSession();
        return $this->userService->createOrUpdateUserFromSession($identifier, $session);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }
}

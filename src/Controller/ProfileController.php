<?php

namespace App\Controller;

use App\Exception\BillingUnavailableException;
use App\Service\BillingClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

#[Route('/profile')]
final class ProfileController extends AbstractController
{
    #[Route(name: 'app_profile', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(BillingClient $billingClient, SessionInterface $session): Response
    {
        $user = $this->getUser();
        $balance = 0;
        $error = null;

        try {
            // Получаем токен из сессии
            $token = $session->get('billing_token');
            
            if ($token) {
                $response = $billingClient->getCurrentUser($token);
                $balance = $response->balance;
            }
        } catch (BillingUnavailableException $e) {
            $error = 'Не удалось получить информацию о балансе';
        }

        // Определяем роль пользователя
        $roles = $user->getRoles();
        $roleName = 'Пользователь';
        
        if (in_array('ROLE_SUPER_ADMIN', $roles)) {
            $roleName = 'Администратор';
        }

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'roleName' => $roleName,
            'balance' => $balance,
            'error' => $error,
        ]);
    }
}

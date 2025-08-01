<?php

namespace App\Controller;

use App\Exception\BillingUnavailableException;
use App\Service\BillingClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile')]
final class ProfileController extends AbstractController
{
    #[Route(name: 'app_profile', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(BillingClient $billingClient, SessionInterface $session): Response
    {
        $user = $this->getUser();
        $balance = null;
        $error = null;

        try {
            $token = $session->get('billing_token');
            if ($token) {
                $response = $billingClient->getCurrentUser($token);
                $balance = $response->balance;
            }
        } catch (BillingUnavailableException) {
            $error = 'Сервис временно недоступен';
        }

        $roles = $user->getRoles();
        $roleName = in_array('ROLE_SUPER_ADMIN', $roles) ? 'Администратор' : 'Пользователь';

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'roleName' => $roleName,
            'balance' => $balance,
            'error' => $error,
        ]);
    }

    #[Route('/transactions', name: 'app_profile_transactions', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function transactions(BillingClient $billingClient, SessionInterface $session): Response
    {
        $transactions = [];
        $error = null;

        try {
            $token = $session->get('billing_token');
            if ($token) {
                $transactions = $billingClient->getTransactions($token);
            }
        } catch (BillingUnavailableException) {
            $error = 'Сервис временно недоступен';
        }

        return $this->render('profile/transactions.html.twig', [
            'transactions' => $transactions,
            'error' => $error,
        ]);
    }
}

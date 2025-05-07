<?php

namespace App\Controller;

use App\Dto\RegistrationDto;
use App\Form\RegistrationType;
use App\Service\RegistrationService;
use App\Service\RateLimiter\RegistrationRateLimiter;
use App\Security\BillingAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Если пользователь уже авторизован, перенаправляем в профиль
        if ($this->getUser()) {
            return $this->redirectToRoute('app_profile');
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request, RegistrationService $registrationService, RegistrationRateLimiter $registrationRateLimiter, SessionInterface $session, UserAuthenticatorInterface $authenticator, BillingAuthenticator $formAuthenticator): Response
    {
        // Если пользователь уже авторизован, перенаправляем в профиль
        if ($this->getUser()) {
            return $this->redirectToRoute('app_profile');
        }

        $registrationDto = new RegistrationDto();
        $form = $this->createForm(RegistrationType::class, $registrationDto);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // Проверяем rate limiting
            $clientIp = $request->getClientIp() ?? 'unknown';
            if (!$registrationRateLimiter->checkLimit($clientIp)) {
                $this->addFlash('error', 'Слишком много попыток регистрации. Попробуйте позже.');
                return $this->render('security/register.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            if ($form->isValid()) {
                $result = $registrationService->registerUser($registrationDto, $session);
                
                if ($result['success']) {
                    $this->addFlash('success', 'Регистрация прошла успешно!');
                    return $authenticator->authenticateUser(
                        $result['user'],
                        $formAuthenticator,
                        $request
                    );
                } else {
                    foreach ($result['errors'] as $error) {
                        $this->addFlash('error', $error);
                    }
                }
            }
            // Если форма не валидна или есть ошибки API, форма автоматически сохранит введенные данные
        }

        return $this->render('security/register.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}

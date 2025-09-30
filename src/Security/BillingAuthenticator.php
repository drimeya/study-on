<?php

namespace App\Security;

use App\Exception\BillingUnavailableException;
use App\Exception\BillingApiException;
use App\Security\BillingCredentials;
use App\Security\UserProvider;
use App\Service\BillingClient;
use App\Service\RateLimiter\LoginRateLimiter;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class BillingAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private BillingClient $billingClient,
        private UserProvider $userProvider,
        private LoginRateLimiter $loginRateLimiter
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->getPayload()->getString('email');
        $password = $request->getPayload()->getString('password');
        
        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        if (!$this->loginRateLimiter->checkLimit($email)) {
            throw new CustomUserMessageAuthenticationException('Слишком много попыток входа. Попробуйте позже.');
        }

        try {
            $response = $this->billingClient->auth($email, $password);

            $request->getSession()->set('billing_token', $response->token);
            $request->getSession()->set('billing_roles', $response->roles);
            $request->getSession()->set('billing_refresh_token', $response->refreshToken);

            $this->loginRateLimiter->resetAttempts($email);
        } catch (BillingApiException) {
            throw new CustomUserMessageAuthenticationException('Неверный логин или пароль.');
        } catch (BillingUnavailableException) {
            throw new CustomUserMessageAuthenticationException('Сервис временно недоступен. Попробуйте авторизоваться позднее.');
        }

        return new Passport(
            new UserBadge($email, [$this->userProvider, 'loadUserByIdentifier']),
            new BillingCredentials($email, $password),
            [
                new CsrfTokenBadge('authenticate', $request->getPayload()->getString('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_course_index'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}

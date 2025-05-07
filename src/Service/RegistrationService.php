<?php

namespace App\Service;

use App\Dto\RegistrationDto;
use App\Exception\BillingApiException;
use App\Exception\BillingUnavailableException;
use App\Service\BillingClientInterface;
use App\Service\UserService;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Psr\Log\LoggerInterface;

class RegistrationService
{
    public function __construct(
        private BillingClientInterface $billingClient,
        private UserService $userService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Регистрирует нового пользователя
     *
     * @param RegistrationDto $registrationData
     * @param SessionInterface $session
     * @return array{success: bool, user?: object, errors?: array}
     */
    public function registerUser(RegistrationDto $registrationData, SessionInterface $session): array
    {
        try {
            $response = $this->billingClient->register($registrationData->email, $registrationData->password);
            
            // Сохраняем токен и роли в сессии
            $session->set('billing_token', $response->token);
            $session->set('billing_roles', $response->roles);
            
            // Загружаем пользователя
            $user = $this->userService->createOrUpdateUserFromSession($registrationData->email, $session);
            
            return [
                'success' => true,
                'user' => $user
            ];
        } catch (BillingUnavailableException $e) {
            return [
                'success' => false,
                'errors' => ['Сервис временно недоступен. Попробуйте зарегистрироваться позднее']
            ];
        } catch (BillingApiException $e) {
            // Бизнес-ошибка от биллинга (например, пользователь уже существует) — можно показать текст
            return [
                'success' => false,
                'errors' => [$e->getMessage()]
            ];
        } catch (\Throwable $e) {
            // Внутренняя ошибка (Doctrine, инфраструктура и т.п.) — логируем и показываем общее сообщение
            $this->logger->error('Ошибка при регистрации пользователя', [
                'exception' => $e,
            ]);

            return [
                'success' => false,
                'errors' => ['Произошла внутренняя ошибка при регистрации. Попробуйте позже.']
            ];
        }
    }
}

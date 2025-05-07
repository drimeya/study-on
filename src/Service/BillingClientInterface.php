<?php

namespace App\Service;

use App\Dto\BillingAuthResponse;
use App\Dto\BillingUserResponse;
use App\Exception\BillingUnavailableException;

interface BillingClientInterface
{
    /**
     * Авторизация пользователя
     */
    public function auth(string $email, string $password): BillingAuthResponse;

    /**
     * Регистрация пользователя
     */
    public function register(string $email, string $password): BillingAuthResponse;

    /**
     * Получение информации о текущем пользователе
     */
    public function getCurrentUser(string $token): BillingUserResponse;
}

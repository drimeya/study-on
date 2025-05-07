<?php

namespace App\Service;

use App\Dto\BillingAuthResponse;
use App\Dto\BillingResponse;
use App\Dto\BillingUserResponse;
use App\Exception\BillingApiException;
use App\Exception\BillingUnavailableException;

class BillingClient implements BillingClientInterface
{
    private string $billingUrl;

    public function __construct(string $billingUrl)
    {
        $this->billingUrl = $billingUrl;
    }

    /**
     * Выполняет HTTP запрос к API биллинга
     *
     * @param string $endpoint
     * @param array $data
     * @param string $method
     * @param string|null $token
     * @return BillingResponse
     * @throws BillingUnavailableException
     */
    public function request(string $endpoint, array $data = [], string $method = 'GET', ?string $token = null): BillingResponse
    {
        // Валидация входных параметров
        $this->validateEndpoint($endpoint);
        $this->validateMethod($method);
        $this->validateData($data);
        
        $url = $this->billingUrl . $endpoint;
        
        $ch = curl_init();
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);
        
        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            throw new BillingUnavailableException('Ошибка подключения к сервису биллинга: ' . $error);
        }
        
        if ($response === false) {
            throw new BillingUnavailableException('Сервис временно недоступен');
        }
        
        $decodedResponse = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new BillingUnavailableException('Ошибка парсинга ответа от сервиса биллинга');
        }
        
        // Обработка ошибок API
        $this->handleApiError($httpCode, $decodedResponse);
        
        return BillingResponse::fromArray([
            'code' => $httpCode,
            'data' => $decodedResponse
        ]);
    }

    /**
     * Авторизация пользователя
     *
     * @param string $email
     * @param string $password
     * @return BillingAuthResponse
     * @throws BillingUnavailableException
     */
    public function auth(string $email, string $password): BillingAuthResponse
    {
        $response = $this->request('/api/v1/auth', [
            'username' => $email,
            'password' => $password
        ], 'POST');
        
        return BillingAuthResponse::fromArray($response->data);
    }

    /**
     * Регистрация пользователя
     *
     * @param string $email
     * @param string $password
     * @return BillingAuthResponse
     * @throws BillingUnavailableException
     */
    public function register(string $email, string $password): BillingAuthResponse
    {
        $response = $this->request('/api/v1/register', [
            'username' => $email,
            'password' => $password
        ], 'POST');
        
        return BillingAuthResponse::fromArray($response->data);
    }

    /**
     * Получение информации о текущем пользователе
     *
     * @param string $token
     * @return BillingUserResponse
     * @throws BillingUnavailableException
     */
    public function getCurrentUser(string $token): BillingUserResponse
    {
        $response = $this->request('/api/v1/users/current', [], 'GET', $token);
        
        return BillingUserResponse::fromArray($response->data);
    }

    /**
     * Валидирует endpoint
     */
    private function validateEndpoint(string $endpoint): void
    {
        if (!preg_match('/^\/api\/v1\/[a-zA-Z0-9\/_-]+$/', $endpoint)) {
            throw new \InvalidArgumentException('Invalid endpoint format: ' . $endpoint);
        }
    }

    /**
     * Валидирует HTTP метод
     */
    private function validateMethod(string $method): void
    {
        $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        if (!in_array(strtoupper($method), $allowedMethods)) {
            throw new \InvalidArgumentException('Invalid HTTP method: ' . $method);
        }
    }

    /**
     * Валидирует данные запроса
     */
    private function validateData(array $data): void
    {
        // Проверяем глубину массива (не более 3 уровней)
        if ($this->getArrayDepth($data) > 3) {
            throw new \InvalidArgumentException('Data array is too deep');
        }
    }

    /**
     * Получает глубину массива
     */
    private function getArrayDepth(array $array): int
    {
        $maxDepth = 1;
        foreach ($array as $value) {
            if (is_array($value)) {
                $depth = $this->getArrayDepth($value) + 1;
                if ($depth > $maxDepth) {
                    $maxDepth = $depth;
                }
            }
        }
        return $maxDepth;
    }

    /**
     * Обрабатывает ошибки API
     */
    private function handleApiError(int $httpCode, array $response): void
    {
        if ($httpCode >= 400) {
            $message = $response['message'] ?? 'Unknown API error';
            $errorCode = $response['code'] ?? $httpCode;
            
            switch ($httpCode) {
                case 400:
                    throw new BillingApiException('Bad request: ' . $message, 400);
                case 401:
                    throw new BillingApiException('Unauthorized: ' . $message, 401);
                case 403:
                    throw new BillingApiException('Forbidden: ' . $message, 403);
                case 404:
                    throw new BillingApiException('Not found: ' . $message, 404);
                case 422:
                    throw new BillingApiException('Validation error: ' . $message, 422);
                case 429:
                    throw new BillingApiException('Rate limit exceeded: ' . $message, 429);
                case 500:
                    throw new BillingUnavailableException('Internal server error: ' . $message);
                default:
                    throw new BillingUnavailableException('API error: ' . $message);
            }
        }
    }
} 
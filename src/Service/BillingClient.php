<?php

namespace App\Service;

use App\Dto\BillingAuthResponse;
use App\Dto\BillingCourseResponse;
use App\Dto\BillingResponse;
use App\Dto\BillingTransactionResponse;
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
    public function request(string $endpoint, array $data = [], string $method = 'GET', ?string $token = null, array $queryParams = []): BillingResponse
    {
        $url = $this->billingUrl . $endpoint;
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }
        
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
        
        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, empty($data) ? '' : json_encode($data));
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
     * Обновляет истекший JWT-токен с помощью refresh-токена
     *
     * @param string $refreshToken
     * @return BillingAuthResponse
     * @throws BillingUnavailableException|BillingApiException
     */
    public function refreshToken(string $refreshToken): BillingAuthResponse
    {
        $url = $this->billingUrl . '/api/v1/token/refresh';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'refresh_token=' . urlencode($refreshToken),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $response === false) {
            throw new BillingUnavailableException('Ошибка подключения к сервису биллинга: ' . $error);
        }

        $decodedResponse = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new BillingUnavailableException('Ошибка парсинга ответа от сервиса биллинга');
        }

        $this->handleApiError($httpCode, $decodedResponse);

        return BillingAuthResponse::fromArray($decodedResponse);
    }

    /**
     * Получить список всех курсов из биллинга
     *
     * @return BillingCourseResponse[]
     * @throws BillingUnavailableException
     */
    public function getCourses(?string $token = null): array
    {
        $response = $this->request('/api/v1/courses', [], 'GET', $token);

        return array_map(
            fn (array $d) => BillingCourseResponse::fromArray($d),
            $response->data
        );
    }

    /**
     * Получить данные одного курса по символьному коду
     *
     * @throws BillingUnavailableException
     */
    public function getCourse(string $code, ?string $token = null): ?BillingCourseResponse
    {
        try {
            $response = $this->request('/api/v1/courses/' . $code, [], 'GET', $token);
            return BillingCourseResponse::fromArray($response->data);
        } catch (BillingApiException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Оплатить курс от имени авторизованного пользователя
     *
     * @return array{success: bool, course_type: string, expires_at: string|null}
     * @throws BillingApiException если недостаточно средств (406) или курс не найден (404)
     * @throws BillingUnavailableException
     */
    public function payCourse(string $code, string $token): array
    {
        $response = $this->request('/api/v1/courses/' . $code . '/pay', [], 'POST', $token);
        return $response->data;
    }

    /**
     * История транзакций текущего пользователя
     *
     * @param array{type?: string, course_code?: string, skip_expired?: bool} $filters
     * @return BillingTransactionResponse[]
     * @throws BillingUnavailableException
     */
    public function getTransactions(string $token, array $filters = []): array
    {
        $queryParams = [];
        if (!empty($filters)) {
            $queryParams['filter'] = $filters;
        }

        $response = $this->request('/api/v1/transactions', [], 'GET', $token, $queryParams);

        return array_map(
            fn (array $d) => BillingTransactionResponse::fromArray($d),
            $response->data
        );
    }

    /**
     * Создать курс в биллинге (только для администратора)
     *
     * @throws BillingUnavailableException|BillingApiException
     */
    public function createCourse(string $token, string $code, string $title, string $type, ?float $price = null): void
    {
        $data = ['code' => $code, 'title' => $title, 'type' => $type];
        if ($price !== null) {
            $data['price'] = $price;
        }

        $this->request('/api/v1/courses', $data, 'POST', $token);
    }

    /**
     * Обновить курс в биллинге (только для администратора)
     *
     * @throws BillingUnavailableException|BillingApiException
     */
    public function updateCourse(string $token, string $currentCode, string $newCode, string $title, string $type, ?float $price = null): void
    {
        $data = ['code' => $newCode, 'title' => $title, 'type' => $type];
        if ($price !== null) {
            $data['price'] = $price;
        }

        $this->request('/api/v1/courses/' . $currentCode, $data, 'POST', $token);
    }

    /**
     * Обрабатывает ошибки API
     */
    protected function handleApiError(int $httpCode, array $response): void
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
                case 406:
                    throw new BillingApiException($message, 406);
                case 409:
                    throw new BillingApiException('Conflict: ' . $message, 409);
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
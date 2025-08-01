<?php

namespace App\Service;

class JwtService
{
    /**
     * Декодирует payload JWT-токена без проверки подписи.
     * Возвращает массив данных или null при некорректном формате.
     */
    public function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        // Base64url → base64 → decode
        $payload = str_replace(['-', '_'], ['+', '/'], $parts[1]);
        $padding = (4 - strlen($payload) % 4) % 4;
        $payload = base64_decode($payload . str_repeat('=', $padding));

        if ($payload === false) {
            return null;
        }

        $data = json_decode($payload, true);

        return is_array($data) ? $data : null;
    }

    /**
     * Возвращает true если токен уже истёк или истечёт в течение $bufferSeconds секунд.
     * Запас нужен, чтобы успеть обновить токен до последующих запросов к биллингу.
     */
    public function isExpired(string $token, int $bufferSeconds = 60): bool
    {
        $payload = $this->decode($token);

        if ($payload === null || !isset($payload['exp'])) {
            return true;
        }

        return $payload['exp'] < (time() + $bufferSeconds);
    }

    /**
     * Извлекает поле username из payload токена.
     */
    public function getUsername(string $token): ?string
    {
        $payload = $this->decode($token);
        return $payload['username'] ?? null;
    }

    /**
     * Извлекает роли из payload токена.
     */
    public function getRoles(string $token): array
    {
        $payload = $this->decode($token);
        return $payload['roles'] ?? [];
    }
}

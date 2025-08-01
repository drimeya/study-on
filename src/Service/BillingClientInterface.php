<?php

namespace App\Service;

use App\Dto\BillingAuthResponse;
use App\Dto\BillingCourseResponse;
use App\Dto\BillingTransactionResponse;
use App\Dto\BillingUserResponse;
use App\Exception\BillingUnavailableException;

interface BillingClientInterface
{
    public function auth(string $email, string $password): BillingAuthResponse;

    public function register(string $email, string $password): BillingAuthResponse;

    public function getCurrentUser(string $token): BillingUserResponse;

    public function refreshToken(string $refreshToken): BillingAuthResponse;

    /**
     * @return BillingCourseResponse[]
     */
    public function getCourses(?string $token = null): array;

    public function getCourse(string $code, ?string $token = null): ?BillingCourseResponse;

    /**
     * @return array{success: bool, course_type: string, expires_at: string|null}
     */
    public function payCourse(string $code, string $token): array;

    /**
     * @return BillingTransactionResponse[]
     */
    public function getTransactions(string $token, array $filters = []): array;
}

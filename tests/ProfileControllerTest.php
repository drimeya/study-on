<?php

namespace App\Tests;

use App\DataFixtures\CourseFixtures;
use App\DataFixtures\LessonFixtures;
use App\Service\RateLimiter\LoginRateLimiter;
use App\Tests\Mock\BillingClientMock;
use Symfony\Component\HttpFoundation\Response;

class ProfileControllerTest extends AbstractTest
{
    protected function getFixtures(): array
    {
        return [
            CourseFixtures::class,
            LessonFixtures::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $billingMock = new BillingClientMock('');
        static::getClient()->getContainer()->set('App\Service\BillingClient', $billingMock);

        $loginLimiter = static::getContainer()->get(LoginRateLimiter::class);
        $loginLimiter->resetAttempts('test@example.com');
        $loginLimiter->resetAttempts('admin@example.com');
    }

    // -------------------------------------------------------------------------
    // Страница профиля
    // -------------------------------------------------------------------------

    /**
     * Неавторизованный пользователь перенаправляется на логин.
     */
    public function testProfileUnauthenticatedRedirects(): void
    {
        static::getClient()->request('GET', '/profile');
        $this->assertResponseRedirects('/login');
    }

    /**
     * На странице профиля отображается баланс из биллинга.
     */
    public function testProfileShowsBalance(): void
    {
        $client = static::getClient();
        $this->loginAsUser();

        $client->request('GET', '/profile');
        $this->assertResponseIsSuccessful();

        // Баланс 1000 ₽ должен быть на странице (в strong внутри td)
        $this->assertStringContainsString('1000', $client->getResponse()->getContent());
    }

    /**
     * На странице профиля есть ссылка на историю транзакций.
     */
    public function testProfileHasTransactionHistoryLink(): void
    {
        $client = static::getClient();
        $this->loginAsUser();

        $crawler = $client->request('GET', '/profile');
        $this->assertResponseIsSuccessful();

        $link = $crawler->selectLink('История транзакций');
        $this->assertGreaterThan(0, $link->count(), 'Ссылка на историю транзакций должна присутствовать');
    }

    // -------------------------------------------------------------------------
    // Страница истории транзакций
    // -------------------------------------------------------------------------

    /**
     * Неавторизованный пользователь перенаправляется на логин.
     */
    public function testTransactionHistoryUnauthenticatedRedirects(): void
    {
        static::getClient()->request('GET', '/profile/transactions');
        $this->assertResponseRedirects('/login');
    }

    /**
     * У пользователя есть начальный депозит — он виден в истории.
     */
    public function testTransactionHistoryShowsInitialDeposit(): void
    {
        $client = static::getClient();
        $this->loginAsUser();

        $crawler = $client->request('GET', '/profile/transactions');
        $this->assertResponseIsSuccessful();

        // Таблица существует
        $this->assertSelectorExists('table');

        // Есть строка с начислением
        $this->assertSelectorExists('.badge.bg-success'); // бейдж "Пополнение"

        // Сумма депозита 1000 есть в таблице
        $text = $crawler->filter('table')->text();
        $this->assertStringContainsString('1000', $text);
    }

    /**
     * После оплаты курса транзакция "Списание" появляется в истории.
     */
    public function testTransactionHistoryShowsPaymentAfterPurchase(): void
    {
        $client = static::getClient();
        $this->loginAsUser();

        // Оплачиваем course-2 (buy, price 200)
        $client->request('POST', '/courses/course-2/pay');
        $client->followRedirect();

        $crawler = $client->request('GET', '/profile/transactions');
        $this->assertResponseIsSuccessful();

        // Должен быть бейдж "Списание"
        $this->assertSelectorExists('.badge.bg-warning');

        // Сумма 159 (цена course-2) должна быть в таблице
        $text = $crawler->filter('table')->text();
        $this->assertStringContainsString('159', $text);
    }

    /**
     * В истории транзакций ссылка на оплаченный курс присутствует.
     */
    public function testTransactionHistoryHasLinkToCourse(): void
    {
        $client = static::getClient();
        $this->loginAsUser();

        // Оплачиваем course-1 (rent)
        $client->request('POST', '/courses/course-1/pay');
        $client->followRedirect();

        $crawler = $client->request('GET', '/profile/transactions');
        $this->assertResponseIsSuccessful();

        // Должна быть ссылка на курс course-1
        $courseLinks = $crawler->filter('a[href*="/courses/course-1"]');
        $this->assertGreaterThan(0, $courseLinks->count(), 'Должна быть ссылка на оплаченный курс');
    }

    /**
     * История транзакций содержит и пополнения, и списания одновременно.
     */
    public function testTransactionHistoryShowsBothTypesAfterPayment(): void
    {
        $client = static::getClient();
        $this->loginAsUser();

        // Делаем платёж
        $client->request('POST', '/courses/course-2/pay');
        $client->followRedirect();

        $crawler = $client->request('GET', '/profile/transactions');
        $this->assertResponseIsSuccessful();

        // Должны быть оба типа бейджей
        $this->assertSelectorExists('.badge.bg-success');  // Пополнение
        $this->assertSelectorExists('.badge.bg-warning');   // Списание
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function loginAsUser(): void
    {
        $client = static::getClient();
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Войти')->form();
        $form['email'] = 'test@example.com';
        $form['password'] = 'password123';
        $client->submit($form);
        $client->followRedirect();
    }
}

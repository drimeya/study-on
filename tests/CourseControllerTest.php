<?php

namespace App\Tests;

use App\DataFixtures\CourseFixtures;
use App\DataFixtures\LessionFixtures;
use App\Service\RateLimiter\LoginRateLimiter;
use App\Tests\Mock\BillingClientMock;
use Symfony\Component\HttpFoundation\Response;

class CourseControllerTest extends AbstractTest
{
    private BillingClientMock $billingMock;

    protected function getFixtures(): array
    {
        return [
            CourseFixtures::class,
            LessionFixtures::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->billingMock = new BillingClientMock('');
        static::getClient()->getContainer()->set('App\Service\BillingClient', $this->billingMock);

        $loginLimiter = static::getContainer()->get(LoginRateLimiter::class);
        $loginLimiter->resetAttempts('admin@example.com');
        $loginLimiter->resetAttempts('test@example.com');
    }

    // -------------------------------------------------------------------------
    // Существующие тесты (структура курсов и CRUD)
    // -------------------------------------------------------------------------

    public function testCourseIndex(): void
    {
        $crawler = static::getClient()->request('GET', '/courses');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertCount(5, $crawler->filter('.course-item'));
    }

    public function testCourseShow(): void
    {
        $crawler = static::getClient()->request('GET', '/courses/course-1');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSelectorTextContains('h1', 'Doctrine ORM');
        $this->assertCount(4, $crawler->filter('.lesson-item'));
    }

    public function testCourseNotFound(): void
    {
        static::getClient()->request('GET', '/courses/999');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testAddLessonWithoutAuth(): void
    {
        static::getClient()->request('GET', '/lessons/new/1');
        $this->assertResponseRedirects('/login');
    }

    public function testAddLessonWithAuth(): void
    {
        $client = static::getClient();
        $this->loginAsAdmin();

        $crawler = $client->request('GET', '/courses/course-0');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $link = $crawler->selectLink('Добавить урок')->link();
        $crawler = $client->click($link);
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $form = $crawler->selectButton('Сохранить')->form();
        $form['lesson[name]'] = 'Новый урок';
        $form['lesson[content]'] = 'Содержимое нового урока';
        $form['lesson[sort]'] = 1;

        $client->submit($form);
        $this->assertResponseRedirect();
        $client->followRedirect();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSelectorTextContains('.lesson-item', 'Новый урок');
    }

    public function testCreateCourseWithoutAuth(): void
    {
        static::getClient()->request('GET', '/courses/new');
        $this->assertResponseRedirects('/login');
    }

    public function testCreateCourseWithInvalidData(): void
    {
        $client = static::getClient();
        $this->loginAsAdmin();

        $crawler = $client->request('GET', '/courses/new');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $form = $crawler->selectButton('Сохранить')->form();
        $form['course[name]'] = '';
        $form['course[code]'] = '';

        $crawler = $client->submit($form);
        $this->assertSelectorExists('.form-error-message');
    }

    public function testCreateCourseWithValidData(): void
    {
        $client = static::getClient();
        $this->loginAsAdmin();

        $crawler = $client->request('GET', '/courses/new');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $form = $crawler->selectButton('Сохранить')->form();
        $form['course[name]'] = 'Новый курс';
        $form['course[code]'] = 'new-course';

        $client->submit($form);
        $this->assertResponseRedirect();
        $crawler = $client->followRedirect();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSelectorTextContains('h1', 'Новый курс');
    }

    public function testEditCourseWithoutAuth(): void
    {
        static::getClient()->request('GET', '/courses/course-0/edit');
        $this->assertResponseRedirects('/login');
    }

    public function testEditCourse(): void
    {
        $client = static::getClient();
        $this->loginAsAdmin();

        $crawler = $client->request('GET', '/courses/course-0/edit');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $form = $crawler->selectButton('Обновить')->form();
        $form['course[name]'] = 'Обновленный курс';

        $client->submit($form);
        $this->assertResponseRedirect();
        $client->followRedirect();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSelectorTextContains('h1', 'Обновленный курс');
    }

    public function testDeleteCourseWithoutAuth(): void
    {
        $em = static::getContainer()->get('doctrine')->getManager();
        $course = $em->getRepository(\App\Entity\Course::class)->findOneBy([]);
        static::getClient()->request('POST', '/courses/'.$course->getId(), ['_token' => 'fake_token']);
        $this->assertResponseRedirects('/login');
    }

    public function testDeleteCourse(): void
    {
        $client = static::getClient();
        $this->loginAsAdmin();

        $crawler = $client->request('GET', '/courses/course-0');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $form = $crawler->filter('form button.btn-danger')->form();
        $client->submit($form);
        $this->assertResponseRedirect();
        $client->followRedirect();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSelectorNotExists('.course-item:contains("Изучение Symfony")');
    }

    // -------------------------------------------------------------------------
    // Новые тесты: биллинг-данные на странице списка курсов
    // -------------------------------------------------------------------------

    /**
     * Неавторизованный пользователь видит бейджи с ценами/типами курсов.
     */
    public function testCourseIndexShowsBillingBadges(): void
    {
        $crawler = static::getClient()->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        // Бесплатные курсы (course-0, course-3)
        $this->assertSelectorExists('.badge.bg-success');
        // Курсы с ценой (course-1, course-2, course-4)
        $this->assertSelectorExists('.badge.bg-light');
    }

    /**
     * Авторизованный пользователь видит цены и статус неоплаченных курсов.
     */
    public function testCourseIndexShowsPricesForAuthenticatedUser(): void
    {
        $client = static::getClient();
        $this->loginAsUser();

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        // Бесплатные курсы
        $freeCount = $crawler->filter('.badge.bg-success')->count();
        $this->assertGreaterThanOrEqual(2, $freeCount); // course-0 и course-3

        // Неоплаченные платные курсы показывают цену через badge-secondary
        $paidCount = $crawler->filter('.badge.bg-secondary')->count();
        $this->assertGreaterThanOrEqual(3, $paidCount); // course-1, course-2, course-4
    }

    /**
     * После оплаты аренды курс показывает "Арендовано до" на странице списка.
     */
    public function testCourseIndexShowsRentedStatusAfterPayment(): void
    {
        $client = static::getClient();
        $this->loginAsUser();

        // Оплачиваем аренду course-1 (rent, price 100)
        $client->request('POST', '/courses/course-1/pay');

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        // После оплаты аренды должен появиться бейдж bg-info
        $this->assertSelectorExists('.badge.bg-info');
        $this->assertSelectorTextContains('.badge.bg-info', 'Арендовано до');
    }

    /**
     * После покупки курс показывает "Куплено" на странице списка.
     */
    public function testCourseIndexShowsBoughtStatusAfterPayment(): void
    {
        $client = static::getClient();
        $this->loginAsUser();

        // Оплачиваем покупку course-2 (buy, price 200)
        $client->request('POST', '/courses/course-2/pay');

        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        // После покупки должен появиться badge bg-primary "Куплено"
        $this->assertSelectorExists('.badge.bg-primary');
        $this->assertSelectorTextContains('.badge.bg-primary', 'Куплено');
    }

    // -------------------------------------------------------------------------
    // Новые тесты: страница курса — блок оплаты
    // -------------------------------------------------------------------------

    /**
     * Бесплатный курс не имеет кнопки оплаты ни для гостя, ни для авторизованного.
     */
    public function testCourseShowFreeCourseHasNoPayButton(): void
    {
        $client = static::getClient();

        // Гость
        $client->request('GET', '/courses/course-0');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('[data-bs-target="#payModal"]');

        // Авторизованный пользователь
        $this->loginAsUser();
        $client->request('GET', '/courses/course-0');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('[data-bs-target="#payModal"]');
    }

    /**
     * Гость видит ссылку "Войти для покупки" на платном курсе.
     */
    public function testCourseShowPaidCourseGuestSeesLoginLink(): void
    {
        $crawler = static::getClient()->request('GET', '/courses/course-1');
        $this->assertResponseIsSuccessful();
        // Ссылка имеет класс btn-outline-secondary и ведёт на /login
        $this->assertSelectorExists('a.btn-outline-secondary[href="/login"]');
        $this->assertSelectorNotExists('[data-bs-target="#payModal"]');
    }

    /**
     * Авторизованный пользователь с достаточным балансом видит кнопку оплаты.
     */
    public function testCourseShowPaidCourseAuthenticatedSeesPayButton(): void
    {
        $client = static::getClient();
        $this->loginAsUser();

        // Арендуемый курс (course-1, rent)
        $crawler = $client->request('GET', '/courses/course-1');
        $this->assertResponseIsSuccessful();
        $btn = $crawler->filter('[data-bs-target="#payModal"]');
        $this->assertGreaterThan(0, $btn->count(), 'Кнопка оплаты должна присутствовать');
        $this->assertStringContainsString('Арендовать', $btn->text());
        $this->assertStringContainsString('99', $btn->text()); // цена 99.90

        // Покупаемый курс (course-2, buy)
        $crawler = $client->request('GET', '/courses/course-2');
        $this->assertResponseIsSuccessful();
        $btn2 = $crawler->filter('[data-bs-target="#payModal"]');
        $this->assertGreaterThan(0, $btn2->count(), 'Кнопка оплаты должна присутствовать');
        $this->assertStringContainsString('Купить', $btn2->text());
    }

    /**
     * Кнопка оплаты задизаблена, если у пользователя недостаточно средств.
     */
    public function testCourseShowPayButtonDisabledWithInsufficientBalance(): void
    {
        $client = static::getClient();

        // Обнуляем баланс через уже зарегистрированный mock (не создаём новый — service already initialized)
        $this->billingMock->setUserBalance('test@example.com', 0.0);

        $this->loginAsUser();

        $crawler = $client->request('GET', '/courses/course-1');
        $this->assertResponseIsSuccessful();

        // Кнопка должна быть задизаблена
        $disabledBtn = $crawler->filter('[data-bs-target="#payModal"][disabled]');
        $this->assertGreaterThan(0, $disabledBtn->count(), 'Кнопка должна быть disabled при нулевом балансе');
    }

    // -------------------------------------------------------------------------
    // Новые тесты: оплата курса
    // -------------------------------------------------------------------------

    /**
     * Успешная оплата — редирект на страницу курса с flash-сообщением.
     */
    public function testCoursePaySuccess(): void
    {
        $client = static::getClient();
        $this->loginAsUser();

        $client->request('POST', '/courses/course-2/pay');
        $this->assertResponseRedirects('/courses/course-2');

        $crawler = $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.alert-success', 'Курс успешно оплачен');
    }

    /**
     * Оплата при нулевом балансе — flash с сообщением об ошибке.
     */
    public function testCoursePayInsufficientFunds(): void
    {
        $client = static::getClient();

        // Обнуляем баланс через уже зарегистрированный mock
        $this->billingMock->setUserBalance('test@example.com', 0.0);

        $this->loginAsUser();

        $client->request('POST', '/courses/course-1/pay');
        $this->assertResponseRedirects('/courses/course-1');

        $crawler = $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.alert-danger', 'недостаточно средств');
    }

    /**
     * После оплаты страница курса показывает "Куплено" вместо кнопки.
     */
    public function testCourseShowAfterBuyPayment(): void
    {
        $client = static::getClient();
        $this->loginAsUser();

        $client->request('POST', '/courses/course-2/pay');
        $client->followRedirect();

        // Повторно заходим — кнопки нет, показывается "Куплено"
        $crawler = $client->request('GET', '/courses/course-2');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('[data-bs-target="#payModal"]');
        $this->assertSelectorTextContains('.alert-success', 'Куплено');
    }

    /**
     * После оплаты аренды страница курса показывает "Арендовано до".
     */
    public function testCourseShowAfterRentPayment(): void
    {
        $client = static::getClient();
        $this->loginAsUser();

        $client->request('POST', '/courses/course-1/pay');
        $client->followRedirect();

        $crawler = $client->request('GET', '/courses/course-1');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('[data-bs-target="#payModal"]');
        $this->assertSelectorTextContains('.alert-info', 'Арендовано до');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function loginAsAdmin(): void
    {
        $client = static::getClient();
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Войти')->form();
        $form['email'] = 'admin@example.com';
        $form['password'] = 'admin123';
        $client->submit($form);
        $client->followRedirect();
    }

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

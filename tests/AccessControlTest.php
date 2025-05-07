<?php

namespace App\Tests;

use App\Tests\Mock\BillingClientMock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AccessControlTest extends WebTestCase
{
    private $client;
    private $billingMock;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();

        $this->billingMock = new BillingClientMock('');
        $this->client->getContainer()->set(
            'App\Service\BillingClient',
            $this->billingMock
        );
    }

    public function testUnauthenticatedUserCannotAccessLessons(): void
    {
        // Пытаемся получить доступ к уроку без авторизации
        $this->client->request('GET', '/lessons/1');
        
        // Должны быть перенаправлены на страницу логина
        $this->assertResponseRedirects('/login');
    }

    public function testUnauthenticatedUserCannotAccessProfile(): void
    {
        // Пытаемся получить доступ к профилю без авторизации
        $this->client->request('GET', '/profile');
        
        // Должны быть перенаправлены на страницу логина
        $this->assertResponseRedirects('/login');
    }

    public function testUnauthenticatedUserCanAccessCourses(): void
    {
        // Список курсов должен быть доступен без авторизации
        $this->client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();
    }

    public function testUnauthenticatedUserCanAccessCourseDetails(): void
    {
        // Детали курса должны быть доступны без авторизации.
        // Берем реальный курс из базы вместо жестко прошитого кода.
        $em = static::getContainer()->get('doctrine')->getManager();
        $course = $em->getRepository(\App\Entity\Course::class)->findOneBy([]);
        $this->client->request('GET', '/courses/'.$course->getCode());
        $this->assertResponseIsSuccessful();
    }

    public function testRegularUserCannotAccessAdminActions(): void
    {
        // Авторизуемся как обычный пользователь
        $this->loginAsUser('test@example.com', 'password123');

        // Пытаемся создать новый курс
        $this->client->request('GET', '/courses/new');
        $this->assertResponseStatusCodeSame(403);

        // Пытаемся отредактировать существующий курс
        $this->client->request('GET', '/courses/course-0/edit');
        $this->assertResponseStatusCodeSame(403);

        // Пытаемся удалить существующий курс
        $this->client->request('POST', '/courses/1', ['_token' => 'fake_token']);
        $status = $this->client->getResponse()->getStatusCode();
        // В зависимости от наличия данных это может быть 403 (доступ запрещен) или 404 (курс не найден)
        $this->assertTrue(in_array($status, [403, 404], true));
    }

    public function testRegularUserCannotAccessLessonAdminActions(): void
    {
        // Авторизуемся как обычный пользователь
        $this->loginAsUser('test@example.com', 'password123');

        // Пытаемся создать новый урок
        $this->client->request('GET', '/lessons/new/1');
        $this->assertResponseStatusCodeSame(403);

        // Пытаемся отредактировать урок
        $this->client->request('GET', '/lessons/1/edit');
        $status = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($status, [403, 404], true));
        
        // Пытаемся удалить урок
        $this->client->request('POST', '/lessons/1', ['_token' => 'fake_token']);
        $status = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(in_array($status, [403, 404], true));
    }

    public function testAdminCanAccessAdminActions(): void
    {
        // Авторизуемся как админ
        $this->loginAsUser('admin@example.com', 'admin123');

        // Берем реальный курс из базы
        $em = static::getContainer()->get('doctrine')->getManager();
        $course = $em->getRepository(\App\Entity\Course::class)->findOneBy([]);

        // Админ должен иметь доступ к созданию курса
        $this->client->request('GET', '/courses/new');
        $this->assertResponseIsSuccessful();

        // Админ должен иметь доступ к редактированию курса
        $this->client->request('GET', '/courses/'.$course->getCode().'/edit');
        $this->assertResponseIsSuccessful();

        // Админ должен иметь доступ к созданию урока
        $this->client->request('GET', '/lessons/new/1');
        $this->assertResponseIsSuccessful();
    }

    public function testAuthenticatedUserCanAccessLessons(): void
    {
        // Авторизуемся как обычный пользователь
        $this->loginAsUser('test@example.com', 'password123');

        // Авторизованный пользователь должен иметь доступ к урокам:
        // переходим на страницу существующего курса и кликаем по первому уроку.
        $em = static::getContainer()->get('doctrine')->getManager();
        $course = $em->getRepository(\App\Entity\Course::class)->findOneBy([]);

        $crawler = $this->client->request('GET', '/courses/'.$course->getCode());
        $this->assertGreaterThan(0, $crawler->filter('.lesson-item a')->count());
        $lessonLink = $crawler->filter('.lesson-item a')->first()->link();
        $this->client->click($lessonLink);
        $this->assertResponseIsSuccessful();
    }

    public function testAuthenticatedUserCanAccessProfile(): void
    {
        // Авторизуемся как обычный пользователь
        $this->loginAsUser('test@example.com', 'password123');

        // Авторизованный пользователь должен иметь доступ к профилю
        $this->client->request('GET', '/profile');
        $this->assertResponseIsSuccessful();
    }

    public function testDirectLessonAccessWithoutAuthentication(): void
    {
        // Пытаемся получить прямой доступ к уроку по ID
        $this->client->request('GET', '/lessons/999');
        
        // Должны быть перенаправлены на страницу логина
        $this->assertResponseRedirects('/login');
    }

    public function testAdminInterfaceNotVisibleToRegularUsers(): void
    {
        // Авторизуемся как обычный пользователь
        $this->loginAsUser('test@example.com', 'password123');

        // Переходим на страницу существующего курса
        $em = static::getContainer()->get('doctrine')->getManager();
        $course = $em->getRepository(\App\Entity\Course::class)->findOneBy([]);
        $crawler = $this->client->request('GET', '/courses/'.$course->getCode());
        
        // Проверяем, что кнопки администрирования не видны
        $this->assertSelectorNotExists('a[href*="/edit"]');
        $this->assertSelectorNotExists('button[type="submit"]');
    }

    public function testAdminInterfaceVisibleToAdmins(): void
    {
        // Авторизуемся как админ
        $this->loginAsUser('admin@example.com', 'admin123');

        // Переходим на страницу любого существующего курса
        $em = static::getContainer()->get('doctrine')->getManager();
        $course = $em->getRepository(\App\Entity\Course::class)->findOneBy([]);
        $crawler = $this->client->request('GET', '/courses/'.$course->getCode());
        
        // Проверяем, что кнопки администрирования видны (кнопка редактирования курса)
        $this->assertSelectorExists('a[href$="/edit"]');
    }

    private function loginAsUser(string $email, string $password): void
    {
        $crawler = $this->client->request('GET', '/login');
        
        $form = $crawler->selectButton('Войти')->form();
        $form['email'] = $email;
        $form['password'] = $password;

        $this->client->submit($form);
        
        // Следуем редиректу
        $this->client->followRedirect();
    }
}

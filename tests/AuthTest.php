<?php

namespace App\Tests;

use App\Tests\Mock\BillingClientMock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class AuthTest extends WebTestCase
{
    private $client;
    private $billingMock;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot(); // Важно для подмены сервиса

        // Подменяем BillingClient на mock
        $this->billingMock = new BillingClientMock('');
        $this->client->getContainer()->set(
            'App\Service\BillingClient',
            $this->billingMock
        );
    }

    public function testLoginPageAccessible(): void
    {
        $crawler = $this->client->request('GET', '/login');
        $this->assertResponseIsSuccessful();
        // Проверяем заголовок страницы через title, чтобы не зависеть от верстки
        $this->assertStringContainsString('Вход', $crawler->filter('title')->text());
    }

    public function testLoginWithValidCredentials(): void
    {
        $crawler = $this->client->request('GET', '/login');
        
        $form = $crawler->selectButton('Войти')->form();
        $form['email'] = 'test@example.com';
        $form['password'] = 'password123';

        $this->client->submit($form);
        
        // Проверяем редирект после успешной авторизации
        $this->assertResponseRedirects('/courses');
        
        // Проверяем, что пользователь авторизован
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    public function testLoginWithInvalidCredentials(): void
    {
        $crawler = $this->client->request('GET', '/login');
        
        $form = $crawler->selectButton('Войти')->form();
        $form['email'] = 'test@example.com';
        $form['password'] = 'wrongpassword';

        $this->client->submit($form);
        
        // Ожидаем редирект обратно на страницу логина
        $this->assertResponseRedirects('/login');
        $this->client->followRedirect();

        // Проверяем, что на форме отображается сообщение об ошибке
        $this->assertSelectorExists('.alert-danger');
    }

    public function testLoginWithNonExistentUser(): void
    {
        $crawler = $this->client->request('GET', '/login');
        
        $form = $crawler->selectButton('Войти')->form();
        $form['email'] = 'nonexistent@example.com';
        $form['password'] = 'password123';

        $this->client->submit($form);
        
        // Ожидаем редирект обратно на страницу логина
        $this->assertResponseRedirects('/login');
        $this->client->followRedirect();

        // Проверяем наличие сообщения об ошибке
        $this->assertSelectorExists('.alert-danger');
    }

    public function testLoginAsAdmin(): void
    {
        $crawler = $this->client->request('GET', '/login');
        
        $form = $crawler->selectButton('Войти')->form();
        $form['email'] = 'admin@example.com';
        $form['password'] = 'admin123';

        $this->client->submit($form);
        
        // Проверяем редирект после успешной авторизации
        $this->assertResponseRedirects('/courses');
        
        // Проверяем, что пользователь авторизован как админ
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    public function testLogout(): void
    {
        // Сначала авторизуемся
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Войти')->form();
        $form['email'] = 'test@example.com';
        $form['password'] = 'password123';
        $this->client->submit($form);

        // Теперь выходим
        $this->client->request('GET', '/logout');
        
        // Проверяем редирект на страницу логина
        $this->assertResponseRedirects('/login');
    }

    public function testRedirectAuthenticatedUserFromLogin(): void
    {
        // Авторизуемся
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Войти')->form();
        $form['email'] = 'test@example.com';
        $form['password'] = 'password123';
        $this->client->submit($form);

        // Пытаемся снова зайти на страницу логина
        $this->client->request('GET', '/login');
        
        // Уже авторизованный пользователь должен быть перенаправлен в профиль
        $this->assertResponseRedirects('/profile');
    }

    public function testRedirectAuthenticatedUserFromRegister(): void
    {
        // Авторизуемся
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Войти')->form();
        $form['email'] = 'test@example.com';
        $form['password'] = 'password123';
        $this->client->submit($form);

        // Пытаемся зайти на страницу регистрации
        $this->client->request('GET', '/register');
        
        // Уже авторизованный пользователь должен быть перенаправлен в профиль
        $this->assertResponseRedirects('/profile');
    }
}

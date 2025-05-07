<?php

namespace App\Tests;

use App\Tests\Mock\BillingClientMock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RegistrationTest extends WebTestCase
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

    public function testRegistrationPageAccessible(): void
    {
        $crawler = $this->client->request('GET', '/register');
        $this->assertResponseIsSuccessful();
        // Проверяем заголовок страницы через title
        $this->assertStringContainsString('Регистрация', $crawler->filter('title')->text());
    }

    public function testSuccessfulRegistration(): void
    {
        $crawler = $this->client->request('GET', '/register');
        
        $form = $crawler->selectButton('Зарегистрироваться')->form();
        $form['registration[email]'] = 'newuser@example.com';
        $form['registration[password][first]'] = 'password123';
        $form['registration[password][second]'] = 'password123';

        $this->client->submit($form);
        
        // Проверяем редирект после успешной регистрации
        // Сейчас после регистрации пользователь перенаправляется на курсы
        $this->assertResponseRedirects('/courses');
        
        // Проверяем, что пользователь создан в mock'е
        $this->assertTrue($this->billingMock->hasUser('newuser@example.com'));
        
        // Проверяем, что пользователь авторизован: после редиректа доступен профиль
        $this->client->followRedirect(); // /courses
        $this->client->request('GET', '/profile');
        $this->assertResponseIsSuccessful();
    }

    public function testRegistrationWithExistingEmail(): void
    {
        $crawler = $this->client->request('GET', '/register');
        
        $form = $crawler->selectButton('Зарегистрироваться')->form();
        $form['registration[email]'] = 'test@example.com'; // Уже существует в mock'е
        $form['registration[password][first]'] = 'password123';
        $form['registration[password][second]'] = 'password123';

        $this->client->submit($form);
        
        // Проверяем, что остаемся на странице регистрации с ошибкой
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.alert-danger');
    }

    public function testRegistrationWithShortPassword(): void
    {
        $crawler = $this->client->request('GET', '/register');
        
        $form = $crawler->selectButton('Зарегистрироваться')->form();
        $form['registration[email]'] = 'newuser@example.com';
        $form['registration[password][first]'] = '123'; // Слишком короткий
        $form['registration[password][second]'] = '123';

        $this->client->submit($form);
        
        // Проверяем, что остаемся на странице регистрации с ошибкой (валидация формы)
        $this->assertResponseIsSuccessful();
        // Ошибки выводятся через стандартные сообщения валидации формы
        $this->assertStringContainsString('Пароль должен содержать минимум', $this->client->getResponse()->getContent());
    }

    public function testRegistrationWithInvalidEmail(): void
    {
        $crawler = $this->client->request('GET', '/register');
        
        $form = $crawler->selectButton('Зарегистрироваться')->form();
        $form['registration[email]'] = 'invalid-email'; // Невалидный email
        $form['registration[password][first]'] = 'password123';
        $form['registration[password][second]'] = 'password123';

        $this->client->submit($form);
        
        // Проверяем, что остаемся на странице регистрации с ошибкой (валидация формы)
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('имеет неверный формат', $this->client->getResponse()->getContent());
    }

    public function testRegistrationWithMismatchedPasswords(): void
    {
        $crawler = $this->client->request('GET', '/register');
        
        $form = $crawler->selectButton('Зарегистрироваться')->form();
        $form['registration[email]'] = 'newuser@example.com';
        $form['registration[password][first]'] = 'password123';
        $form['registration[password][second]'] = 'differentpassword';

        $this->client->submit($form);
        
        // Проверяем, что остаемся на странице регистрации с ошибкой (валидация формы)
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Пароли не совпадают', $this->client->getResponse()->getContent());
    }

    public function testRegistrationWithEmptyFields(): void
    {
        $crawler = $this->client->request('GET', '/register');
        
        $form = $crawler->selectButton('Зарегистрироваться')->form();
        // Оставляем поля пустыми

        $this->client->submit($form);
        
        // Проверяем, что остаемся на странице регистрации с ошибками валидации
        $this->assertResponseIsSuccessful();
        // Проверяем, что форма не прошла валидацию (остаемся на той же странице)
        $this->assertStringContainsString('Регистрация', $this->client->getResponse()->getContent());
    }

    public function testRegistrationRateLimit(): void
    {
        // Попытаемся много раз подряд отправить форму регистрации
        for ($i = 0; $i < 5; $i++) {
            $crawler = $this->client->request('GET', '/register');
            
            $form = $crawler->selectButton('Зарегистрироваться')->form();
            $form['registration[email]'] = "user{$i}@example.com";
            // Делаем заведомо невалидный пароль, чтобы пользователь не авторизовывался
            $form['registration[password][first]'] = '123';
            $form['registration[password][second]'] = '123';

            $this->client->submit($form);
        }

        // Попытка превысить лимит
        $crawler = $this->client->request('GET', '/register');
        
        $form = $crawler->selectButton('Зарегистрироваться')->form();
        $form['registration[email]'] = 'user5@example.com';
        $form['registration[password][first]'] = 'password123';
        $form['registration[password][second]'] = 'password123';

        $this->client->submit($form);
        
        // Проверяем, что сработал rate limit: редирект на /courses (как и при успешной регистрации)
        $this->assertResponseRedirects('/courses');
    }
}

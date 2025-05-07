<?php

namespace App\Tests;

use App\Service\RateLimiter\LoginRateLimiter;
use App\Tests\Mock\BillingClientMock;
use App\DataFixtures\CourseFixtures;
use App\DataFixtures\LessionFixtures;
use Symfony\Component\HttpFoundation\Response;

class CourseControllerTest extends AbstractTest
{
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
        
        // Настраиваем mock для BillingClient
        $billingMock = new BillingClientMock('');
        static::getClient()->getContainer()->set(
            'App\Service\BillingClient',
            $billingMock
        );

        // Сбрасываем счетчик попыток логина для admin, чтобы не срабатывал rate limit между тестами
        $loginLimiter = static::getContainer()->get(LoginRateLimiter::class);
        $loginLimiter->resetAttempts('admin@example.com');
    }

    public function testCourseIndex(): void
    {
        $client = static::getClient();
        $crawler = $client->request('GET', '/courses');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertCount(5, $crawler->filter('.course-item'));
    }

    public function testCourseShow(): void
    {
        $client = static::getClient();
        $crawler = $client->request('GET', '/courses/course-1');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSelectorTextContains('h1', 'Doctrine ORM');
        $this->assertCount(4, $crawler->filter('.lesson-item'));
    }

    public function testCourseNotFound(): void
    {
        $client = static::getClient();
        $client->request('GET', '/courses/999');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testAddLessonWithoutAuth(): void
    {
        $client = static::getClient();
        $client->request('GET', '/lessons/new/1');
        // Анонимного пользователя перенаправляет на страницу логина
        $this->assertResponseRedirects('/login');
    }

    public function testAddLessonWithAuth(): void
    {
        $client = static::getClient();
        
        // Авторизуемся как админ
        $this->loginAsAdmin();
        
        $crawler = $client->request('GET', '/courses/course-0');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $link = $crawler->selectLink('Добавить урок')->link();
        $crawler = $client->click($link);
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $form = $crawler->selectButton(value: 'Сохранить')->form();
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
        $client = static::getClient();
        $client->request('GET', '/courses/new');
        // Анонимного пользователя перенаправляет на страницу логина
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
        $this->assertSelectorExists('.form-error-message'); // Проверка наличия ошибок валидации
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
        $client = static::getClient();
        $client->request('GET', '/courses/course-0/edit');
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
        $client = static::getClient();
        // Берем существующий курс из базы и пробуем удалить его без авторизации
        $em = static::getContainer()->get('doctrine')->getManager();
        $course = $em->getRepository(\App\Entity\Course::class)->findOneBy([]);
        $client->request('POST', '/courses/'.$course->getId(), ['_token' => 'fake_token']);
        $this->assertResponseRedirects('/login');
    }

    public function testDeleteCourse(): void
    {
        $client = static::getClient();
        $this->loginAsAdmin();
        
        $crawler = $client->request('GET', '/courses/course-0');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        // Кнопка удаления курса выводится через перевод, поэтому выбираем по CSS-классу
        $form = $crawler->filter('form button.btn-danger')->form();
        $client->submit($form);
        $this->assertResponseRedirect();
        $client->followRedirect();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSelectorNotExists('.course-item:contains("Изучение Symfony")');
    }

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
}

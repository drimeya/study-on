<?php

namespace App\Tests;

use App\Entity\Course;
use App\Entity\Lesson;
use App\DataFixtures\CourseFixtures;
use App\DataFixtures\LessionFixtures;
use App\Service\RateLimiter\LoginRateLimiter;
use App\Tests\Mock\BillingClientMock;
use Symfony\Component\HttpFoundation\Response;

class LessonControllerTest extends AbstractTest
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

        // Сбрасываем счетчик попыток логина для тестовых пользователей, чтобы не срабатывал rate limit между тестами
        $loginLimiter = static::getContainer()->get(LoginRateLimiter::class);
        $loginLimiter->resetAttempts('admin@example.com');
        $loginLimiter->resetAttempts('test@example.com');
    }

    public function testIndex()
    {
        $client = static::getClient();
        $client->request('GET', '/lessons');

        // Анонимного пользователя перенаправляет на страницу логина (так как /lessons защищен)
        $this->assertResponseRedirects('/login');
    }

    public function testNewLessonWithoutAuth()
    {
        $client = static::getClient();
        $client->request('GET', '/lessons/new/1');
        $this->assertResponseRedirects('/login');
    }

    public function testNewLesson()
    {
        $client = static::getClient();
        $this->loginAsAdmin();
        
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        // Предположим, что у нас есть курс с ID 1
        $course = $entityManager->getRepository(Course::class)->findOneBy(['code' => 'course-0']);

        $crawler = $client->request('GET', '/lessons/new/' . $course->getId());

        // В форме один submit-кнопка, выбираем её по классу
        $form = $crawler->filter('form button.btn-primary')->form([
            'lesson[name]' => 'Новый урок',
            'lesson[content]' => 'Содержимое нового урока',
            'lesson[sort]' => 1,
        ]);

        $client->submit($form);

        $this->assertResponseRedirects('/courses/' . $course->getCode());
        $client->followRedirect();

        $this->assertSelectorTextContains('h1', $course->getName());
    }

    public function testShowLessonWithoutAuth()
    {
        $client = static::getClient();
        $client->request('GET', '/lessons/1');
        $this->assertResponseRedirects('/login');
    }

    public function testShowLesson()
    {
        $client = static::getClient();
        $this->loginAsUser();
        
        // Переходим на страницу первого курса
        $crawler = $client->request('GET', '/courses/course-0');
        
        // Находим ссылку на первый урок и переходим по ней
        $lessonLink = $crawler->filter('.lesson-item a')->first()->link();
        $crawler = $client->click($lessonLink);
        
        // Сохраняем урок для последующих проверок
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $lesson = $entityManager->getRepository(Lesson::class)->findOneBy(['name' => $crawler->filter('h1')->text()]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', $lesson->getName());
    }

    public function testEditLessonWithoutAuth()
    {
        $client = static::getClient();
        $client->request('GET', '/lessons/1/edit');
        $this->assertResponseRedirects('/login');
    }

    public function testEditLesson()
    {
        $client = static::getClient();
        $this->loginAsAdmin();
        
        // Переходим на страницу первого курса
        $crawler = $client->request('GET', '/courses/course-0');
        
        // Находим ссылку на первый урок и переходим по ней
        $lessonLink = $crawler->filter('.lesson-item a')->first()->link();
        $crawler = $client->click($lessonLink);
        
        // Сохраняем урок для последующих проверок
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $lesson = $entityManager->getRepository(Lesson::class)->findOneBy(['name' => $crawler->filter('h1')->text()]);

        // Переходим на страницу редактирования
        $editLink = $crawler->selectLink('Изменить урок')->link();
        $crawler = $client->click($editLink);

        // В форме один submit-кнопка, выбираем её по классу
        $form = $crawler->filter('form button.btn-primary')->form([
            'lesson[name]' => 'Обновленный урок',
            'lesson[content]' => 'Обновленное содержимое урока',
        ]);

        $client->submit($form);
        $this->assertResponseRedirects('/lessons/' . $lesson->getId());
        $client->followRedirect();

        $this->assertSelectorTextContains('h1', 'Обновленный урок');
    }

    public function testDeleteLessonWithoutAuth()
    {
        $client = static::getClient();
        $client->request('POST', '/lessons/1', ['_token' => 'fake_token']);
        $this->assertResponseRedirects('/login');
    }

    public function testDeleteLesson()
    {
        $client = static::getClient();
        $this->loginAsAdmin();
        
        // Переходим на страницу первого курса
        $crawler = $client->request('GET', '/courses/course-0');
        
        // Находим ссылку на первый урок и переходим по ней
        $lessonLink = $crawler->filter('.lesson-item a')->first()->link();
        $crawler = $client->click($lessonLink);
        
        // Сохраняем урок для последующих проверок
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $lesson = $entityManager->getRepository(Lesson::class)->findOneBy(['name' => $crawler->filter('h1')->text()]);

        // Нажимаем на кнопку удаления (кнопка в форме с классом btn-danger)
        $form = $crawler->filter('form button.btn-danger')->form();
        $client->submit($form);

        $this->assertResponseRedirects('/courses/' . $lesson->getCourse()->getCode());
        $client->followRedirect();

        $this->assertSelectorNotExists('.lesson-item:contains("' . $lesson->getName() . '")');
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
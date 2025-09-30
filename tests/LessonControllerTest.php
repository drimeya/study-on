<?php

namespace App\Tests;

use App\DataFixtures\CourseFixtures;
use App\DataFixtures\LessonFixtures;
use App\Entity\Course;
use App\Entity\Lesson;
use App\Service\RateLimiter\LoginRateLimiter;
use App\Tests\Mock\BillingClientMock;
use Symfony\Component\HttpFoundation\Response;

class LessonControllerTest extends AbstractTest
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
        $loginLimiter->resetAttempts('admin@example.com');
        $loginLimiter->resetAttempts('test@example.com');
    }

    // -------------------------------------------------------------------------
    // Существующие тесты
    // -------------------------------------------------------------------------

    public function testIndex(): void
    {
        static::getClient()->request('GET', '/lessons');
        $this->assertResponseRedirects('/login');
    }

    public function testNewLessonWithoutAuth(): void
    {
        static::getClient()->request('GET', '/lessons/new/1');
        $this->assertResponseRedirects('/login');
    }

    public function testNewLesson(): void
    {
        $client = static::getClient();
        $this->loginAsAdmin();

        $em = static::getContainer()->get('doctrine')->getManager();
        $course = $em->getRepository(Course::class)->findOneBy(['code' => 'course-0']);

        $crawler = $client->request('GET', '/lessons/new/' . $course->getId());

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

    public function testShowLessonWithoutAuth(): void
    {
        static::getClient()->request('GET', '/lessons/1');
        $this->assertResponseRedirects('/login');
    }

    public function testShowLesson(): void
    {
        $client = static::getClient();
        $this->loginAsUser();

        // course-0 бесплатный — урок доступен без оплаты
        $crawler = $client->request('GET', '/courses/course-0');

        $lessonLink = $crawler->filter('.lesson-item a')->first()->link();
        $crawler = $client->click($lessonLink);

        $em = static::getContainer()->get('doctrine')->getManager();
        $lesson = $em->getRepository(Lesson::class)->findOneBy(['name' => $crawler->filter('h1')->text()]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', $lesson->getName());
    }

    public function testEditLessonWithoutAuth(): void
    {
        static::getClient()->request('GET', '/lessons/1/edit');
        $this->assertResponseRedirects('/login');
    }

    public function testEditLesson(): void
    {
        $client = static::getClient();
        $this->loginAsAdmin();

        $crawler = $client->request('GET', '/courses/course-0');
        $lessonLink = $crawler->filter('.lesson-item a')->first()->link();
        $crawler = $client->click($lessonLink);

        $em = static::getContainer()->get('doctrine')->getManager();
        $lesson = $em->getRepository(Lesson::class)->findOneBy(['name' => $crawler->filter('h1')->text()]);

        $editLink = $crawler->selectLink('Изменить урок')->link();
        $crawler = $client->click($editLink);

        $form = $crawler->filter('form button.btn-primary')->form([
            'lesson[name]' => 'Обновленный урок',
            'lesson[content]' => 'Обновленное содержимое урока',
        ]);

        $client->submit($form);
        $this->assertResponseRedirects('/lessons/' . $lesson->getId());
        $client->followRedirect();

        $this->assertSelectorTextContains('h1', 'Обновленный урок');
    }

    public function testDeleteLessonWithoutAuth(): void
    {
        static::getClient()->request('POST', '/lessons/1', ['_token' => 'fake_token']);
        $this->assertResponseRedirects('/login');
    }

    public function testDeleteLesson(): void
    {
        $client = static::getClient();
        $this->loginAsAdmin();

        $crawler = $client->request('GET', '/courses/course-0');
        $lessonLink = $crawler->filter('.lesson-item a')->first()->link();
        $crawler = $client->click($lessonLink);

        $em = static::getContainer()->get('doctrine')->getManager();
        $lesson = $em->getRepository(Lesson::class)->findOneBy(['name' => $crawler->filter('h1')->text()]);

        $form = $crawler->filter('form button.btn-danger')->form();
        $client->submit($form);

        $this->assertResponseRedirects('/courses/' . $lesson->getCourse()->getCode());
        $client->followRedirect();

        $this->assertSelectorNotExists('.lesson-item:contains("' . $lesson->getName() . '")');
    }

    // -------------------------------------------------------------------------
    // Новые тесты: контроль доступа к урокам платных курсов
    // -------------------------------------------------------------------------

    /**
     * Урок бесплатного курса доступен авторизованному пользователю без оплаты.
     */
    public function testShowLessonFreeCourseAccessible(): void
    {
        $client = static::getClient();
        $this->loginAsUser();

        $em = static::getContainer()->get('doctrine')->getManager();
        $course = $em->getRepository(Course::class)->findOneBy(['code' => 'course-0']);
        $lesson = $em->getRepository(Lesson::class)->findOneBy(['course' => $course]);

        $client->request('GET', '/lessons/' . $lesson->getId());
        $this->assertResponseIsSuccessful();
    }

    /**
     * Урок платного курса недоступен без оплаты — возвращается 403 Forbidden.
     */
    public function testShowLessonPaidCourseWithoutPaymentForbidden(): void
    {
        $client = static::getClient();
        $this->loginAsUser();

        $em = static::getContainer()->get('doctrine')->getManager();
        $course = $em->getRepository(Course::class)->findOneBy(['code' => 'course-1']);
        $lesson = $em->getRepository(Lesson::class)->findOneBy(['course' => $course]);

        $client->request('GET', '/lessons/' . $lesson->getId());
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * Урок платного курса доступен после успешной оплаты.
     */
    public function testShowLessonPaidCourseAccessibleAfterPayment(): void
    {
        $client = static::getClient();
        $this->loginAsUser();

        $em = static::getContainer()->get('doctrine')->getManager();
        $course = $em->getRepository(Course::class)->findOneBy(['code' => 'course-1']);
        $lesson = $em->getRepository(Lesson::class)->findOneBy(['course' => $course]);

        // Оплачиваем курс
        $client->request('POST', '/courses/course-1/pay');
        $client->followRedirect();

        // Теперь урок должен быть доступен
        $client->request('GET', '/lessons/' . $lesson->getId());
        $this->assertResponseIsSuccessful();
    }

    /**
     * Урок покупаемого курса недоступен без оплаты — возвращается 403 Forbidden.
     */
    public function testShowLessonBuyCourseWithoutPaymentForbidden(): void
    {
        $client = static::getClient();
        $this->loginAsUser();

        $em = static::getContainer()->get('doctrine')->getManager();
        $course = $em->getRepository(Course::class)->findOneBy(['code' => 'course-2']);
        $lesson = $em->getRepository(Lesson::class)->findOneBy(['course' => $course]);

        $client->request('GET', '/lessons/' . $lesson->getId());
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * Урок покупаемого курса доступен после оплаты.
     */
    public function testShowLessonBuyCourseAccessibleAfterPayment(): void
    {
        $client = static::getClient();
        $this->loginAsUser();

        $em = static::getContainer()->get('doctrine')->getManager();
        $course = $em->getRepository(Course::class)->findOneBy(['code' => 'course-2']);
        $lesson = $em->getRepository(Lesson::class)->findOneBy(['course' => $course]);

        // Оплачиваем
        $client->request('POST', '/courses/course-2/pay');
        $client->followRedirect();

        $client->request('GET', '/lessons/' . $lesson->getId());
        $this->assertResponseIsSuccessful();
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

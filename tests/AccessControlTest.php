<?php

namespace App\Tests;

use App\DataFixtures\CourseFixtures;
use App\DataFixtures\LessonFixtures;
use App\Tests\Mock\BillingClientMock;

class AccessControlTest extends AbstractTest
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
        static::getClient()->getContainer()->set(
            'App\Service\BillingClient',
            $billingMock
        );
    }

    public function testUnauthenticatedUserCannotAccessLessons(): void
    {
        static::getClient()->request('GET', '/lessons/1');
        $this->assertResponseRedirects('/login');
    }

    public function testUnauthenticatedUserCannotAccessProfile(): void
    {
        static::getClient()->request('GET', '/profile');
        $this->assertResponseRedirects('/login');
    }

    public function testUnauthenticatedUserCanAccessCourses(): void
    {
        static::getClient()->request('GET', '/courses');
        $this->assertResponseIsSuccessful();
    }

    public function testUnauthenticatedUserCanAccessCourseDetails(): void
    {
        $em = static::getEntityManager();
        $course = $em->getRepository(\App\Entity\Course::class)->findOneBy([]);
        static::getClient()->request('GET', '/courses/' . $course->getCode());
        $this->assertResponseIsSuccessful();
    }

    public function testRegularUserCannotAccessAdminActions(): void
    {
        $this->loginAsUser('test@example.com', 'password123');

        static::getClient()->request('GET', '/courses/new');
        $this->assertResponseStatusCodeSame(403);

        static::getClient()->request('GET', '/courses/course-0/edit');
        $this->assertResponseStatusCodeSame(403);

        $em = static::getEntityManager();
        $course = $em->getRepository(\App\Entity\Course::class)->findOneBy([]);
        static::getClient()->request('POST', '/courses/' . $course->getId(), ['_token' => 'fake_token']);
        $status = static::getClient()->getResponse()->getStatusCode();
        $this->assertTrue(in_array($status, [403, 404], true));
    }

    public function testRegularUserCannotAccessLessonAdminActions(): void
    {
        $this->loginAsUser('test@example.com', 'password123');

        $em = static::getEntityManager();
        $course = $em->getRepository(\App\Entity\Course::class)->findOneBy([]);

        static::getClient()->request('GET', '/lessons/new/' . $course->getId());
        $this->assertResponseStatusCodeSame(403);

        $lesson = $em->getRepository(\App\Entity\Lesson::class)->findOneBy([]);
        static::getClient()->request('GET', '/lessons/' . $lesson->getId() . '/edit');
        $status = static::getClient()->getResponse()->getStatusCode();
        $this->assertTrue(in_array($status, [403, 404], true));

        static::getClient()->request('POST', '/lessons/' . $lesson->getId(), ['_token' => 'fake_token']);
        $status = static::getClient()->getResponse()->getStatusCode();
        $this->assertTrue(in_array($status, [403, 404], true));
    }

    public function testAdminCanAccessAdminActions(): void
    {
        $this->loginAsUser('admin@example.com', 'admin123');

        $em = static::getEntityManager();
        $course = $em->getRepository(\App\Entity\Course::class)->findOneBy([]);

        static::getClient()->request('GET', '/courses/new');
        $this->assertResponseIsSuccessful();

        static::getClient()->request('GET', '/courses/' . $course->getCode() . '/edit');
        $this->assertResponseIsSuccessful();

        static::getClient()->request('GET', '/lessons/new/' . $course->getId());
        $this->assertResponseIsSuccessful();
    }

    public function testAuthenticatedUserCanAccessLessons(): void
    {
        $this->loginAsUser('test@example.com', 'password123');

        $em = static::getEntityManager();
        $course = $em->getRepository(\App\Entity\Course::class)->findOneBy([]);

        $crawler = static::getClient()->request('GET', '/courses/' . $course->getCode());
        $this->assertGreaterThan(0, $crawler->filter('.lesson-item a')->count());
        $lessonLink = $crawler->filter('.lesson-item a')->first()->link();
        static::getClient()->click($lessonLink);
        $this->assertResponseIsSuccessful();
    }

    public function testAuthenticatedUserCanAccessProfile(): void
    {
        $this->loginAsUser('test@example.com', 'password123');
        static::getClient()->request('GET', '/profile');
        $this->assertResponseIsSuccessful();
    }

    public function testDirectLessonAccessWithoutAuthentication(): void
    {
        static::getClient()->request('GET', '/lessons/999');
        $this->assertResponseRedirects('/login');
    }

    public function testAdminInterfaceNotVisibleToRegularUsers(): void
    {
        $this->loginAsUser('test@example.com', 'password123');

        $em = static::getEntityManager();
        $course = $em->getRepository(\App\Entity\Course::class)->findOneBy([]);
        static::getClient()->request('GET', '/courses/' . $course->getCode());

        $this->assertSelectorNotExists('a[href*="/edit"]');
        $this->assertSelectorNotExists('button[type="submit"]');
    }

    public function testAdminInterfaceVisibleToAdmins(): void
    {
        $this->loginAsUser('admin@example.com', 'admin123');

        $em = static::getEntityManager();
        $course = $em->getRepository(\App\Entity\Course::class)->findOneBy([]);
        static::getClient()->request('GET', '/courses/' . $course->getCode());

        $this->assertSelectorExists('a[href$="/edit"]');
    }

    private function loginAsUser(string $email, string $password): void
    {
        $crawler = static::getClient()->request('GET', '/login');

        $form = $crawler->selectButton('Войти')->form();
        $form['email'] = $email;
        $form['password'] = $password;

        static::getClient()->submit($form);
        static::getClient()->followRedirect();
    }
}

<?php

namespace App\Tests;

use App\Entity\Course;
use App\Entity\Lesson;
use App\DataFixtures\CourseFixtures;
use App\DataFixtures\LessionFixtures;
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

    public function testIndex()
    {
        $client = static::getClient();
        $client->request('GET', '/lessons');

        $this->assertResponseStatusCodeSame(Response::HTTP_SEE_OTHER);
    }

    public function testNewLesson()
    {
        $client = static::getClient();
        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        // Предположим, что у нас есть курс с ID 1
        $course = $entityManager->getRepository(Course::class)->findOneBy(['code' => 'course-0']);

        $crawler = $client->request('GET', '/lessons/new/' . $course->getId());

        $form = $crawler->selectButton('Сохранить')->form([
            'lesson[name]' => 'Новый урок',
            'lesson[content]' => 'Содержимое нового урока',
            'lesson[sort]' => 1,
        ]);

        $client->submit($form);

        $this->assertResponseRedirects('/courses/' . $course->getCode());
        $client->followRedirect();

        $this->assertSelectorTextContains('h1', $course->getName());
    }

    public function testShowLesson()
    {
        $client = static::getClient();
        
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

    public function testEditLesson()
    {
        $client = static::getClient();
        
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

        $form = $crawler->selectButton('Обновить')->form([
            'lesson[name]' => 'Обновленный урок',
            'lesson[content]' => 'Обновленное содержимое урока',
        ]);

        $client->submit($form);
        $this->assertResponseRedirects('/lessons/' . $lesson->getId());
        $client->followRedirect();

        $this->assertSelectorTextContains('h1', 'Обновленный урок');
    }

    public function testDeleteLesson()
    {
        $client = static::getClient();
        
        // Переходим на страницу первого курса
        $crawler = $client->request('GET', '/courses/course-0');
        
        // Находим ссылку на первый урок и переходим по ней
        $lessonLink = $crawler->filter('.lesson-item a')->first()->link();
        $crawler = $client->click($lessonLink);
        
        // Сохраняем урок для последующих проверок
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $lesson = $entityManager->getRepository(Lesson::class)->findOneBy(['name' => $crawler->filter('h1')->text()]);

        // Нажимаем на кнопку удаления
        $form = $crawler->selectButton('Удалить урок')->form();
        $client->submit($form);

        $this->assertResponseRedirects('/courses/' . $lesson->getCourse()->getCode());
        $client->followRedirect();

        $this->assertSelectorNotExists('.lesson-item:contains("' . $lesson->getName() . '")');
    }
} 
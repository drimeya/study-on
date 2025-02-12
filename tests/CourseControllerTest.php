<?php

namespace App\Tests;

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

    public function testAddLesson(): void
    {
        $client = static::getClient();
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

    public function testCreateCourseWithInvalidData(): void
    {
        $client = static::getClient();
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

    public function testEditCourse(): void
    {
        $client = static::getClient();
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

    public function testDeleteCourse(): void
    {
        $client = static::getClient();
        $crawler = $client->request('GET', '/courses/course-0');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $form = $crawler->selectButton('Удалить курс')->form();
        $client->submit($form);
        $this->assertResponseRedirect();
        $client->followRedirect();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSelectorNotExists('.course-item:contains("Изучение Symfony")');
    }
}

<?php

namespace App\DataFixtures;

use App\Entity\Course;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CourseFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $names = [
            "Изучение Symfony",
            "Doctrine ORM",
            "Модель данных",
            "Frontend в Symfony",
            "Тестирование"
        ];
        $content = "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.";

        for ($i = 0; $i < 5; $i++) {
            $course = new Course();
            $course->setCode('course-'.$i);
            $course->setName($names[$i]);
            $course->setDescription(substr($content, 0, rand(20, 999)));
            $manager->persist($course);
        }

        $manager->flush();
    }
}

<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\Lesson;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class LessionFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $content = "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Praesent scelerisque porttitor rhoncus. Duis   pretium egestas arcu sed viverra. Ut fringilla odio id felis rhoncus ultrices. Pellentesque elementum quam elit, et accumsan eros sodales sed. Aliquam semper lorem sit amet risus tristique, id euismod purus ultrices. Quisque nibh metus, lobortis id magna a, convallis volutpat elit. Donec interdum enim a pulvinar convallis. Sed vel accumsan ante, id aliquam nulla. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Donec ac pellentesque metus. Nullam eu urna dapibus, laoreet turpis et, suscipit dui. Proin sed fringilla ante. Duis dictum enim a elit lacinia, sed vulputate turpis dapibus.Mauris ac pulvinar neque. Nulla consectetur congue risus at rhoncus. Etiam posuere et ipsum lobortis imperdiet. Integer elementum lectus sed finibus bibendum. Pellentesque purus erat, dignissim non sollicitudin at, molestie vel metus. Mauris pharetra ornare nisl mi.";

        $courses = $manager->getRepository(Course::class)->findAll();

        foreach ($courses as $course) {
            $count = rand(3, 5);

            for ($i = 0; $i < $count; $i++) {
                $lesson = new Lesson();
                $lesson->setName("Очередной урок по курсу " .$course->getName() . $i);
                $lesson->setContent(str_repeat($content, rand(1,5)));
                $lesson->setSort(rand(1, 1000));
                $lesson->setCourse($course);
                $manager->persist($lesson);
            }
        }
        $manager->flush();
    }
}

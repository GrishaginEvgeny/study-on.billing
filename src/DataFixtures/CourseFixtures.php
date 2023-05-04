<?php

namespace App\DataFixtures;

use App\Entity\Course;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class CourseFixtures extends Fixture implements OrderedFixtureInterface
{

    public function getOrder(): int
    {
        return 1;
    }

    public function load(ObjectManager $manager): void
    {

        $courses = [
            'pythonDeveloper' => new Course(),
            'layoutDesigner' => new Course(),
            'webDeveloper' => new Course(),
            'desktopDeveloper' => new Course(),
            'chessPlayer' => new Course(),
        ];

        $courses['pythonDeveloper']
            ->setName('Python-разработчик')
            ->setType(Course::RENT_TYPE)
            ->setCost(99.99);

        $courses['layoutDesigner']
            ->setName('Верстальщик')
            ->setType(Course::FREE_TYPE);


        $courses['webDeveloper']
            ->setName('Веб-разработчик')
            ->setType(Course::BUY_TYPE)
            ->setCost(199.99);

        $courses['desktopDeveloper']
            ->setName('Разработчик десктопных приложений')
            ->setType(Course::BUY_TYPE)
            ->setCost(1990.99);

        $courses['chessPlayer']
            ->setName('Обучение шахматам')
            ->setType(Course::RENT_TYPE)
            ->setCost(1100.99);


        foreach ($courses as $key => $course) {
            $course->setCharacterCode($key);
            $manager->persist($course);
        }

        $manager->flush();

    }
}
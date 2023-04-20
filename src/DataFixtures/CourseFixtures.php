<?php

namespace App\DataFixtures;

use App\Entity\Course;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class CourseFixtures extends Fixture implements FixtureGroupInterface
{

    public static function getGroups(): array
     {
         return ['group1'];
     }

    public function load(ObjectManager $manager): void
    {
        $courses = [
            'pythonDeveloper' => new Course(),
            'layoutDesigner' => new Course(),
            'webDeveloper' => new Course(),
        ];

        $courses['pythonDeveloper']
            ->setCharacterCode('pydev')
            ->setType(2)
            ->setCost(99.99);

        $courses['layoutDesigner']
            ->setCharacterCode('layoutdesigner')
            ->setType(1);


        $courses['webDeveloper']
            ->setCharacterCode('webdev')
            ->setType(3)
            ->setCost(199.99);

        foreach ($courses as $course) {
            $manager->persist($course);
        }

        $manager->flush();

    }
}
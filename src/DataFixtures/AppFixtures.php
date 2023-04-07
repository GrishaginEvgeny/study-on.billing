<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $hasher;

    public function __construct(UserPasswordHasherInterface $hasher)
    {
        $this->hasher = $hasher;
    }

    public function load(ObjectManager $manager): void
    {

        $user = new User();
        $password = $this->hasher->hashPassword($user, 'user');
        $user
            ->setEmail('usualuser@study.com')
            ->setPassword($password)
            ->setRoles(['ROLE_USER'])
            ->setBalance(0.0);

        $admin = new User();
        $password = $this->hasher->hashPassword($admin, 'admin');
        $admin
            ->setEmail('admin@study.com')
            ->setPassword($password)
            ->setRoles(['ROLE_SUPER_ADMIN'])
            ->setBalance(1111111110.0);

        $manager->persist($user);
        $manager->persist($admin);
        $manager->flush();
    }
}

<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;


class UserFixtures extends Fixture implements FixtureGroupInterface
{

    public static function getGroups(): array
    {
        return ['group2'];
    }

    private UserPasswordHasherInterface $hasher;

    public function __construct(
        UserPasswordHasherInterface $hasher,
        RefreshTokenGeneratorInterface $refreshTokenGenerator,
        RefreshTokenManagerInterface $refreshTokenManager)
    {
        $this->hasher = $hasher;
        $this->refreshTokenGenerator = $refreshTokenGenerator;
        $this->refreshTokenManager = $refreshTokenManager;
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

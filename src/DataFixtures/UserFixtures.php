<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Services\PaymentService;
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
    private PaymentService $paymentService;

    /**
     * @param UserPasswordHasherInterface $hasher
     * @param PaymentService $paymentService
     */
    public function __construct(
        UserPasswordHasherInterface $hasher,
        PaymentService $paymentService)
    {
        $this->hasher = $hasher;
        $this->paymentService = $paymentService;
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

        $newUser = new User();
        $password = $this->hasher->hashPassword($newUser, 'user');
        $newUser
            ->setEmail('newuser@study.com')
            ->setPassword($password)
            ->setRoles(['ROLE_USER'])
            ->setBalance(0.0);


        $password = $this->hasher->hashPassword($admin, 'admin');
        $admin
            ->setEmail('admin@study.com')
            ->setPassword($password)
            ->setRoles(['ROLE_SUPER_ADMIN'])
            ->setBalance(0.0);

        $manager->persist($user);
        $manager->persist($admin);
        $manager->persist($newUser);

        $this->paymentService->makeDeposit($user, $_ENV['BASE_BALANCE']);
        $this->paymentService->makeDeposit($newUser, $_ENV['BASE_BALANCE']);
        $this->paymentService->makeDeposit($admin, 111111111111.0);

        $manager->flush();
    }
}

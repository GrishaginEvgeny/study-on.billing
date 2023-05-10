<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class TransactionFixtures extends Fixture implements OrderedFixtureInterface
{
    public function getOrder(): int
    {
        return 2;
    }

    public function load(ObjectManager $manager): void
    {
        $userRepository = $manager->getRepository(User::class);
        $courseRepository = $manager->getRepository(Course::class);


        $transaction = new Transaction();
        $transaction->setType(Transaction::PAYMENT_TYPE)
            ->setCourse($courseRepository->findOneBy(['characterCode' => 'pydev']))
            ->setTransactionUser($userRepository->findOneBy(['email' => 'usualuser@study.com']))
            ->setAmount($courseRepository->findOneBy(['characterCode' => 'pydev'])->getCost())
            ->setCreatedAt(new \DateTime('-6 day -5 hours'))
            ->setExpiredAt(new \DateTime('-6 day -5 hours + 1 week'));
        $manager->persist($transaction);

        $transaction = new Transaction();
        $transaction->setType(Transaction::PAYMENT_TYPE)
            ->setCourse($courseRepository->findOneBy(['characterCode' => 'pydev']))
            ->setTransactionUser($userRepository->findOneBy(['email' => 'usualuser@study.com']))
            ->setAmount($courseRepository->findOneBy(['characterCode' => 'pydev'])->getCost())
            ->setCreatedAt(new \DateTime('-3 week'))
            ->setExpiredAt(new \DateTime('-1 week'));
        $manager->persist($transaction);

        $transaction = new Transaction();
        $transaction->setType(Transaction::PAYMENT_TYPE)
            ->setCourse($courseRepository->findOneBy(['characterCode' => 'pydev']))
            ->setTransactionUser($userRepository->findOneBy(['email' => 'usualuser@study.com']))
            ->setAmount($courseRepository->findOneBy(['characterCode' => 'pydev'])->getCost())
            ->setCreatedAt(new \DateTime('-2 month'))
            ->setExpiredAt(new \DateTime('-1 month -3 weeks'));
        $manager->persist($transaction);

        $transaction = new Transaction();
        $transaction->setType(Transaction::PAYMENT_TYPE)
            ->setCourse($courseRepository->findOneBy(['characterCode' => 'chessPlayer']))
            ->setTransactionUser($userRepository->findOneBy(['email' => 'admin@study.com']))
            ->setAmount($courseRepository->findOneBy(['characterCode' => 'chessPlayer'])->getCost())
            ->setCreatedAt(new \DateTime('-6 day -5 hours'))
            ->setExpiredAt(new \DateTime('-6 day -5 hours + 1 week'));
        $manager->persist($transaction);

        $transaction = new Transaction();
        $transaction->setType(Transaction::PAYMENT_TYPE)
            ->setCourse($courseRepository->findOneBy(['characterCode' => 'desktopDeveloper']))
            ->setTransactionUser($userRepository->findOneBy(['email' => 'admin@study.com']))
            ->setAmount($courseRepository->findOneBy(['characterCode' => 'desktopDeveloper'])->getCost())
            ->setCreatedAt(new \DateTime('-2 weeks'));
        $manager->persist($transaction);

        $manager->flush();
    }
}

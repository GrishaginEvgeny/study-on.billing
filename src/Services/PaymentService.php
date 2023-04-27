<?php
namespace App\Services;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class PaymentService
{
//    Deposit is 1, Payment is 0

    private EntityManagerInterface $entityManager;

    private TransactionRepository $transactionRepository;

    private UserRepository $userRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        TransactionRepository $transactionRepository,
        UserRepository $userRepository)
    {
        $this->entityManager = $entityManager;
        $this->transactionRepository = $transactionRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function makeDeposit(User $user, float $amount): ?Transaction
    {
        try {
            $this->entityManager->getConnection()->beginTransaction();
            $transaction = new Transaction();
            $transaction
                ->setType(1)
                ->setValue($amount)
                ->setDate(new \DateTime('now'))
                ->setTransactionUser($user);
            $user->setBalance($user->getBalance() + $amount);
            $this->transactionRepository->add($transaction, true);
            $this->userRepository->add($user, true);
            $this->entityManager->getConnection()->commit();
            return $transaction;
        } catch (\Exception $e) {
            $this->entityManager->getConnection()->rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function makePayment(User $user, Course $course): ?Transaction
    {
        try {
            $this->entityManager->getConnection()->beginTransaction();
            $transaction = new Transaction();
            $transaction
                ->setType(0)
                ->setValue($course->getCost())
                ->setDate(new \DateTime('now'))
                ->setTransactionUser($user)
                ->setCourse($course);
            $user->setBalance($user->getBalance() - $course->getCost());
            if ($course->getStringedType() === 'rent') {
                $transaction->setValidTo((new \DateTime('now'))
                    ->add(\DateInterval::createFromDateString('1 week')));
            }
            $this->transactionRepository->add($transaction, true);
            $this->userRepository->add($user, true);
            $this->entityManager->getConnection()->commit();
            return $transaction;
        } catch (\Exception $e) {
            $this->entityManager->getConnection()->rollBack();
            throw new \RuntimeException($e->getMessage());
        }
    }
}
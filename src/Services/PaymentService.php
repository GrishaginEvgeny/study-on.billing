<?php

namespace App\Services;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use mysql_xdevapi\Exception;
use PaymentException;

class PaymentService
{
    private EntityManagerInterface $entityManager;
    private TransactionRepository $transactionRepository;
    private UserRepository $userRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        TransactionRepository $transactionRepository,
        UserRepository $userRepository
    ) {
        $this->entityManager = $entityManager;
        $this->transactionRepository = $transactionRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws PaymentException
     */
    public function makeDeposit(User $user, float $amount): ?Transaction
    {
        try {
            $this->entityManager->getConnection()->beginTransaction();
            $transaction = new Transaction();
            $transaction
                ->setType(Transaction::DEPOSIT_TYPE)
                ->setAmount($amount)
                ->setCreatedAt(new \DateTime('now'))
                ->setTransactionUser($user);
            $user->setBalance($user->getBalance() + $amount);
            $this->transactionRepository->add($transaction, true);
            $this->userRepository->add($user, true);
            $this->entityManager->getConnection()->commit();
            return $transaction;
        } catch (\Exception $e) {
            $this->entityManager->getConnection()->rollBack();
            throw new PaymentException($e->getMessage());
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
                ->setType(Transaction::PAYMENT_TYPE)
                ->setAmount($course->getCost())
                ->setCreatedAt(new \DateTime('now'))
                ->setTransactionUser($user)
                ->setCourse($course);
            $user->setBalance($user->getBalance() - $course->getCost());
            if ($course->getTypeCode() === 'rent') {
                $transaction->setExpiredAt((new \DateTime('now'))
                    ->add(\DateInterval::createFromDateString('1 week')));
            }
            $this->transactionRepository->add($transaction, true);
            $this->userRepository->add($user, true);
            $this->entityManager->getConnection()->commit();
            return $transaction;
        } catch (\Exception $e) {
            $this->entityManager->getConnection()->rollBack();
            throw new \PaymentException($e->getMessage());
        }
    }
}

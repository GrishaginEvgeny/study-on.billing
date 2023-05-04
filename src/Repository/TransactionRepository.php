<?php

namespace App\Repository;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Validator\Constraints\DateTime;

/**
 * @extends ServiceEntityRepository<Transaction>
 *
 * @method Transaction|null find($id, $lockMode = null, $lockVersion = null)
 * @method Transaction|null findOneBy(array $criteria, array $orderBy = null)
 * @method Transaction[]    findAll()
 * @method Transaction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    public function add(Transaction $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Transaction $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @param int|null $type
     * @param string|null $characterCode
     * @param bool|null $skipExpired
     * @param User $user
     * @return int|mixed|string
     */
    public function getTransactionsByFilters(
        ?int    $type = null,
        ?string $characterCode = null,
        ?bool   $skipExpired = null,
        User    $user)
    {
        $query = $this->getEntityManager()->createQueryBuilder()
            ->select('t')
            ->from('App\Entity\Transaction', 't')
            ->leftJoin('t.course', 'c', Join::WITH)
            ->andWhere('t.transactionUser = :user')
            ->setParameter('user', $user);

        if (!is_null($type)) {
            $query->andWhere('t.type = :type')
                ->setParameter('type', $type);
        }

        if ($characterCode) {
            $query->andWhere('c.characterCode = :char_code')
                ->setParameter('char_code', $characterCode);
        }

        if ($skipExpired) {
            $query->andWhere('t.expiredAt > :now_date')
                ->setParameter('now_date', new \DateTime('now'));
            if (!$characterCode) {
                $query->orWhere('t.expiredAt is NULL');
            }
        }

        return $query->getQuery()->getResult();
    }

    /**
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getTransactionOnTypeRentWithMaxDate(Course $course, User $user)
    {
        $query = $this->getEntityManager()->createQueryBuilder()
            ->select('t')
            ->from('App\Entity\Transaction', 't')
            ->orderBy('t.expiredAt', 'DESC')
            ->setMaxResults(1)
            ->andWhere('t.transactionUser = :user')
            ->setParameter('user', $user)
            ->andWhere('t.course = :course')
            ->setParameter('course', $course);
        return $query->getQuery()->getOneOrNullResult();
    }

    public function getRentTransactionsExpiresInOneDayOnUser(User $user)
    {
        $query = $this->getEntityManager()->createQueryBuilder()
            ->select('t')
            ->from('App\Entity\Transaction', 't')
            ->andWhere('t.transactionUser = :user')
            ->setParameter('user', $user)
            ->andWhere('t.expiredAt < :plus_one_day_date')
            ->setParameter('plus_one_day_date', new \DateTime('+1 day'));
        return $query->getQuery()->getResult();
    }

    public function getReportAboutCourses()
    {
        $query = $this->getEntityManager()->createQuery('SELECT 
                     c.name AS name,
                     c.type AS type,
                     count(t.id) AS count,
                     sum(t.amount) AS totalSum
              FROM App\Entity\Transaction t,
                     App\Entity\Course c
              WHERE (c.type = :buy OR c.type = :rent)
                  AND t.course = c.id
                  AND t.createdAt BETWEEN :start AND :end
              GROUP BY c.id, c.type')
            ->setParameter('start', new \DateTime('-1 month'))
            ->setParameter('end', new \DateTime('now'))
            ->setParameter('buy', Course::BUY_TYPE)
            ->setParameter('rent', Course::RENT_TYPE);

        return $query->getResult();
    }

//    /**
//     * @return Transaction[] Returns an array of Transaction objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('t.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }
//
//    public function findOneBySomeField($value): ?Transaction
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}

<?php

namespace App\Validator;

use App\Entity\Course;
use App\Entity\Transaction;
use App\ErrorTemplate\ErrorTemplate;
use App\Repository\TransactionRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class RepeatBuyValidator extends ConstraintValidator
{
    private TransactionRepository $transactionRepository;

    public function __construct(TransactionRepository $transactionRepository)
    {
        $this->transactionRepository = $transactionRepository;
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof RepeatBuyConstraint) {
            throw new UnexpectedTypeException($constraint, RepeatBuyConstraint::class);
        }
        $transaction = $this->transactionRepository
            ->findOneBy([
                'transactionUser' => $value->getUser(),
                'course' => $value->getCourse(),
                'type' => Transaction::PAYMENT_TYPE]);
        if (!is_null($transaction) && $value->getCourse()->getType() === Course::BUY_TYPE) {
            $this->context->buildViolation(ErrorTemplate::PURCHASED_COURSE_TEXT)
                ->addViolation();
        }
        if (
            !is_null($transaction) &&
            $transaction->getExpiredAt() > new \DateTimeImmutable("now") &&
            $value->getCourse()->getType() === Course::RENT_TYPE
        ) {
            $this->context->buildViolation(ErrorTemplate::RENTED_COURSE_TEXT)
                ->addViolation();
        }
    }
}

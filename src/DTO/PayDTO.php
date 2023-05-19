<?php

namespace App\DTO;

use App\Entity\Course;
use App\Entity\User;
use App\ErrorTemplate\ErrorTemplate;
use App\Validator\RepeatBuyConstraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @RepeatBuyConstraint()
 */
class PayDTO
{
    private User $user;

    private Course $course;

    private $transaction;

    public function __construct(User $user, Course $course)
    {
        $this->user = $user;
        $this->course = $course;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getCourse()
    {
        return $this->course;
    }

    /**
     * @Assert\Callback()
     */
    public function validate(ExecutionContextInterface $context): void
    {
        if ($this->course->getType() === Course::FREE_TYPE) {
            $context
                ->buildViolation("errors.pay.buy_free_course")
                ->addViolation();
        }

        if ($this->user->getBalance() < $this->course->getCost()) {
            $transactionText = $this->course->getType() === Course::RENT_TYPE ?
                "errors.pay.not_enough_rent" : "errors.pay.not_enough_buy";
            $context
                ->buildViolation($transactionText)
                ->setTranslationDomain('validators')
                ->setParameters([])
                ->addViolation();
        }
    }
}

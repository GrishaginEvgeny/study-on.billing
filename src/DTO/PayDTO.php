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
                ->buildViolation(ErrorTemplate::BUY_FREE_COURSE_TEXT)
                ->addViolation();
        }

        if ($this->user->getBalance() < $this->course->getCost()) {
            $transactionText = $this->course->getType() === Course::RENT_TYPE ?
                ErrorTemplate::NOT_ENOUGH_FOR_RENT_TEXT : ErrorTemplate::NOT_ENOUGH_FOR_BUY_TEXT;
            $context
                ->buildViolation($transactionText)
                ->addViolation();
        }
    }
}

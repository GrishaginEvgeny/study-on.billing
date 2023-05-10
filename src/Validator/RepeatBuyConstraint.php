<?php

namespace App\Validator;

use App\DTO\PayDTO;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class RepeatBuyConstraint extends Constraint
{
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }

    public function validatedBy(): string
    {
        return RepeatBuyValidator::class;
    }
}

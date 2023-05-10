<?php

namespace App\Validator;

use App\ErrorTemplate\ErrorTemplate;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class UniqueCodeConstraint extends Constraint
{
    public string $message = ErrorTemplate::COURSE_EXIST_TEXT;

    public function validatedBy(): string
    {
        return UniqueCodeValidator::class;
    }
}

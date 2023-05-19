<?php

namespace App\Validator;

use App\ErrorTemplate\ErrorTemplate;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class UniqueCodeConstraint extends Constraint
{
    public string $message = "errors.course.slug.non_unique";

    public function validatedBy(): string
    {
        return UniqueCodeValidator::class;
    }
}

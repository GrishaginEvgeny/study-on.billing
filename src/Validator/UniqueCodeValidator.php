<?php

namespace App\Validator;

use App\Repository\CourseRepository;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Constraint;

class UniqueCodeValidator extends ConstraintValidator
{
    private CourseRepository $courseRepository;

    public function __construct(CourseRepository $courseRepository)
    {
        $this->courseRepository = $courseRepository;
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueCodeConstraint) {
            throw new UnexpectedTypeException($constraint, UniqueCodeConstraint::class);
        }
        $courseInDB = $this->courseRepository->findOneBy(['characterCode' => $value]);
        if (!is_null($courseInDB)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ string }}', $value)
                ->addViolation();
        }
    }
}

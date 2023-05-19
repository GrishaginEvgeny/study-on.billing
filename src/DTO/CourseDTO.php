<?php

namespace App\DTO;

use App\ErrorTemplate\ErrorTemplate;
use App\Validator\UniqueCodeConstraint;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class CourseDTO
{
    /**
     * @Serializer\Type("string")
     * @Assert\Length(
     *     min=1,
     *     max=255,
     *     minMessage="errors.course.slug.too_tiny",
     *     maxMessage="errors.course.slug.too_big")
     * @Assert\Regex(
     *     pattern="/^[A-Za-z0-9]+$/",
     *     message="errors.course.slug.wrong_regex")
     * @UniqueCodeConstraint()
     */
    public string $code;

    /**
     * @Serializer\Type("string")
     */
    public string $type;

    /**
     * @Serializer\Type("string")
     * @Assert\Length(
     *     min=1,
     *     max=255,
     *     minMessage="errors.course.name.too_tiny",
     *     maxMessage="errors.course.name.too_big")
     */
    public string $title;

    /**
     * @Serializer\Type("float")
     */
    public float $price;

    /**
     * @Assert\Callback()
     */
    public function validate(ExecutionContextInterface $context): void
    {

        if ($this->type === 'free' && $this->price != 0) {
            $context
                ->buildViolation("errors.course.price.free_with_cost")
                ->setTranslationDomain('validators')
                ->setParameters([])
                ->atPath('price')
                ->addViolation();
        }

        if (($this->type === 'rent' || $this->type === 'buy') && $this->price <= 0) {
            $context
                ->buildViolation("errors.course.price.buyable_without_cost")
                ->setTranslationDomain('validators')
                ->setParameters([])
                ->atPath('price')
                ->addViolation();
        }

        if (!in_array($this->type, ['rent', 'free', 'buy'])) {
            $context
                ->buildViolation("errors.course.type.wrong_type")
                ->setTranslationDomain('validators')
                ->setParameters([])
                ->atPath('type')
                ->addViolation();
        }
    }
}

<?php

namespace App\DTO;

use App\ErrorTemplate\ErrorTemplate;
use App\Validator\UniqueCodeConstraint;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class CourseDTO
{
    /**
     * @Serializer\Type("string")
     * @Assert\Length(
     *     min=1,
     *     max=255,
     *     minMessage="Поле Cимвольный код не должно быть пустым.",
     *     maxMessage="Поле Cимвольный код не должно длинной более {{ limit }} символов.")
     * @Assert\Regex(
     *     pattern="/^[A-Za-z0-9]+$/",
     *     message="В поле Cимвольный код могут содержаться только цифры и латиница.")
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
     *     minMessage="Поле Название не должно быть пустым.",
     *     maxMessage="Поле Название не должно длинной более {{ limit }} символов.")
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
                ->buildViolation(ErrorTemplate::FREE_WITH_PRICE_TEXT)
                ->atPath('price')
                ->addViolation();
        }

        if (($this->type === 'rent' || $this->type === 'buy') && $this->price <= 0) {
            $context
                ->buildViolation(ErrorTemplate::COSTABLE_WITH_ZERO_COST_TEXT)
                ->atPath('price')
                ->addViolation();
        }

        if (!in_array($this->type, ['rent', 'free', 'buy'])) {
            $context
                ->buildViolation(ErrorTemplate::WRONG_TYPE_TEXT)
                ->atPath('type')
                ->addViolation();
        }
    }
}

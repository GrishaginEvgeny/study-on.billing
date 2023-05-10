<?php

namespace App\DTO;

use App\Entity\Transaction;
use JMS\Serializer\Annotation as Serializer;
use JMS\Serializer\Annotation\Accessor;

class TransactionDTO
{
    /**
     * @Serializer\Type("string")
     */
    public $type;

    /**
     * @Serializer\Type("string")
     */
    public $course_code;

    /**
     * @Serializer\Type("bool")
     */
    public $skip_expired;
}

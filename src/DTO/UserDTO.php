<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use JMS\Serializer\Annotation as Serializer;

class UserDTO
{
    /**
     * @Serializer\Type("string")
     * @Assert\NotBlank(message="errors.user.email.non_blank")
     * @Assert\Email(message="errors.user.email.wrong_email")
     */
    public ?string $username;

    /**
     * @Serializer\Type("string")
     * @Assert\NotBlank(message="errors.user.password.non_blank")
     * @Assert\Length(min=6, minMessage="errors.user.password.too_tiny")
     * @Assert\Regex(pattern="/(?=.*[0-9])(?=.*[.!@#$%^&*])(?=.*[a-z])(?=.*[A-Z])[0-9a-zA-Z!@#$%^&*.]+$/",
     *      message="errors.user.password.wrong_regex")
     */
    public ?string $password;
}

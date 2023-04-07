<?php

namespace App\DTO;

use JMS\Serializer\Annotation\Exclude;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use JMS\Serializer\Annotation as Serializer;

class UserDTO
{
    /**
     * @Serializer\Type("string")
     * @Assert\NotBlank(message="Поле e-mail не может быт пустым.")
     * @Assert\Email( message="Поле e-mail содержит некорректные данные.")
     */
    public ?string $username;

    /**
     * @Serializer\Type("string")
     * @Assert\NotBlank(message="Поле Пароль не может быт пустым.")
     * @Assert\Length(min=6, minMessage="Пароль должен содержать минимум {{ limit }} символов.")
     * @Assert\Regex(pattern="/(?=.*[0-9])(?=.*[.!@#$%^&*])(?=.*[a-z])(?=.*[A-Z])[0-9a-zA-Z!@#$%^&*.]+$/",
     *      message="Пароль должен содержать как один из спец. символов (.!@#$%^&*), прописную и строчные буквы латинского алфавита и цифру.")
     */
    public ?string $password;

}
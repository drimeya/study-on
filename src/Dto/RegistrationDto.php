<?php

namespace App\Dto;

use App\Validator\Email;
use Symfony\Component\Validator\Constraints as Assert;

class RegistrationDto
{
    #[Assert\NotBlank(message: 'Email обязателен')]
    #[Email]
    public ?string $email = '';

    #[Assert\NotBlank(message: 'Пароль обязателен')]
    #[Assert\Length(
        min: 6,
        minMessage: 'Пароль должен содержать минимум {{ limit }} символов'
    )]
    public ?string $password = '';

    public function __construct(?string $email = '', ?string $password = '')
    {
        $this->email = $email;
        $this->password = $password;
    }
}

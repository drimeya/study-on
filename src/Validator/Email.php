<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Email extends Constraint
{
    public string $message = 'Email "{{ value }}" имеет неверный формат.';

    public function validatedBy(): string
    {
        return EmailValidator::class;
    }
}

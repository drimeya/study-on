<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class EmailValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof Email) {
            throw new UnexpectedTypeException($constraint, Email::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        // Проверяем базовый формат email
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->addViolation();
            return;
        }

        // Проверяем длину email
        if (strlen($value) > 254) {
            $this->context->buildViolation('Email слишком длинный')
                ->addViolation();
            return;
        }

        // Проверяем на подозрительные символы
        if (preg_match('/[<>"\']/', $value)) {
            $this->context->buildViolation('Email содержит недопустимые символы')
                ->addViolation();
            return;
        }

        // Проверяем домен
        $parts = explode('@', $value);
        if (count($parts) !== 2) {
            $this->context->buildViolation('Неверный формат email')
                ->addViolation();
            return;
        }

        $domain = $parts[1];
        
        // Проверяем, что домен не является IP адресом
        if (filter_var($domain, FILTER_VALIDATE_IP)) {
            $this->context->buildViolation('Домен не может быть IP адресом')
                ->addViolation();
            return;
        }

        // Проверяем, что домен имеет правильный формат
        if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$/', $domain)) {
            $this->context->buildViolation('Неверный формат домена')
                ->addViolation();
            return;
        }
    }
}

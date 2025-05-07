<?php

namespace App\Form;

use App\Dto\RegistrationDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Введите email'
                ]
            ])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => [
                    'label' => 'Пароль (минимум 6 символов)',
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Введите пароль'
                    ]
                ],
                'second_options' => [
                    'label' => 'Подтвердите пароль',
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Повторите пароль'
                    ]
                ],
                'invalid_message' => 'Пароли не совпадают',
                'mapped' => true
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RegistrationDto::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'registration'
        ]);
    }
}

<?php

namespace App\Form;

use App\Entity\Course;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CourseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code')
            ->add('name')
            ->add('description')
            ->add('billingType', ChoiceType::class, [
                'label'    => 'Тип курса',
                'mapped'   => false,
                'required' => false,
                'choices'  => [
                    'Бесплатный' => 'free',
                    'Аренда'     => 'rent',
                    'Покупка'    => 'buy',
                ],
                'data' => $options['billing_type'],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('billingPrice', NumberType::class, [
                'label'    => 'Стоимость (₽)',
                'mapped'   => false,
                'required' => false,
                'data'     => $options['billing_price'],
                'attr'     => ['placeholder' => 'Для бесплатных курсов оставьте пустым'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'    => Course::class,
            'billing_type'  => 'free',
            'billing_price' => null,
        ]);

        $resolver->setAllowedTypes('billing_type',  ['string', 'null']);
        $resolver->setAllowedTypes('billing_price', ['float', 'int', 'null']);
    }
}

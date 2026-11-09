<?php

declare(strict_types=1);

namespace App\Contact\UI\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<ContactFormData>
 */
final class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'contact.form.name', 'empty_data' => ''])
            ->add('email', EmailType::class, ['label' => 'contact.form.email', 'empty_data' => ''])
            ->add('message', TextareaType::class, ['label' => 'contact.form.message', 'empty_data' => ''])
            ->add('product', HiddenType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContactFormData::class,
            'attr' => ['novalidate' => true],
        ]);
    }
}

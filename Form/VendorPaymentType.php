<?php 
// src/KimaiPlugin/LhgPayrollBundle/Form/VendorPaymentType.php

namespace KimaiPlugin\LhgPayrollBundle\Form;

use KimaiPlugin\LhgPayrollBundle\Entity\Vendor;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use KimaiPlugin\LhgPayrollBundle\Entity\VendorPayment;

class VendorPaymentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('project', EntityType::class, [
                'class' => 'App\Entity\Project', // Adjust the class to your Project entity
                'choice_label' => 'name', // Customize the label property
            ])
            ->add('vendor', EntityType::class, [
                'class' => Vendor::class, // Use the Vendor entity
                'choice_label' => 'name', // Customize the label property
            ])
            ->add('billingType')
            ->add('amount')
            ->add('note')
            ->add('description');
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => VendorPayment::class,
        ]);
    }
}

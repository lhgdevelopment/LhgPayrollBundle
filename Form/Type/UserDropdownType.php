<?php 

namespace KimaiPlugin\LhgPayrollBundle\Form\Type;

use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserDropdownType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'class' => User::class,
            'choice_label' => 'username', // Adjust this to the property you want to use as the choice label
            'choice_value' => 'id', // Use the 'id' property as the value
            'placeholder' => 'Select a user', // Optional placeholder
        ]);
    }

    public function getParent()
    {
        return EntityType::class;
    }
}

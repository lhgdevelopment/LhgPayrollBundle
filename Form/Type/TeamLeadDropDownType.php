<?php 

namespace KimaiPlugin\LhgPayrollBundle\Form\Type;

use App\User\UserService;
use KimaiPlugin\LhgPayrollBundle\Service\UserService as LhgUserService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Intl\Locales;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Custom form field type to select the language.
 */
class TeamLeadDropDownType extends AbstractType
{
    private $userService;

    public function __construct(LhgUserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $choices = $this->userService->getAllUsers();  

        $resolver->setDefaults([
            'choices' => $choices,
            'label' => 'Select user', 
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return ChoiceType::class;
    }
}

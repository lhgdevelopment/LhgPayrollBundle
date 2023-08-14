<?php 

namespace KimaiPlugin\LhgPayrollBundle\Form;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Entity\User;
use App\Repository\UserRepository;
use DateTime;

class PayrollFilterType extends AbstractType
{
    private $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $users = $this->userRepository->findAll(); 

        $userChoices = [];
        foreach ($users as $user) {
            $userChoices[$user->getUsername()] = $user;
        }

        $selectedDate = new DateTime();
        if ($options['data'] && $options['data']['selectedDate']) {
            $selectedDate = $options['data']['selectedDate'];
        }

        $builder
            ->add('selectedUser', EntityType::class, [
                'class' => User::class,
                'choices' => $userChoices,
                'choice_label' => 'username',
                'label' => 'Select User',
                'required' => true,
            ])
            ->add('selectedDate', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Select Date',
                'required' => true,
                'data' => $selectedDate,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
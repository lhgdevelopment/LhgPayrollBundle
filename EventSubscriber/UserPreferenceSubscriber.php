<?php 

// src/EventSubscriber/UserPreferenceSubscriber.php

namespace KimaiPlugin\LhgPayrollBundle\EventSubscriber;

use App\Entity\UserPreference;
use App\Event\UserPreferenceEvent;
use KimaiPlugin\LhgPayrollBundle\Form\Type\TeamLeadDropDownType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;

class UserPreferenceSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            UserPreferenceEvent::class => ['loadUserPreferences', 200],
        ];
    }

    public function loadUserPreferences(UserPreferenceEvent $event): void
    {
        $event->addPreference(
            (new UserPreference())
                ->setName('lhg_payroll.approvval_flow.team_lead') 
                ->setOrder(910)
                ->setType(TeamLeadDropDownType::class)
                ->setEnabled(true)
                ->setOptions(['help' => 'Select team lead for this user', 'label' => 'Team Lead']) 
                ->setSection('lhgPayroll')
        );

        $event->addPreference(
            (new UserPreference())
                ->setName('lhg_payroll.salary') 
                ->setOrder(910)
                ->setType(TeamLeadDropDownType::class)
                ->setEnabled(true)
                ->setOptions(['help' => 'Select team lead for this user', 'label' => 'Team Lead']) 
                ->setSection('lhgPayroll')
        );

        $event->addPreference(
            (new UserPreference())
                ->setName('lhg_payroll.payroll.salary') 
                ->setOrder(930)
                ->setType(MoneyType::class)
                ->setEnabled(true)
                ->setOptions(['help' => 'Enter Salary Amount', 'label' => 'Salary']) 
                ->setSection('rate')
        );
    }
}

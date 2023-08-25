<?php 

// src/EventSubscriber/UserPreferenceSubscriber.php

namespace KimaiPlugin\LhgPayrollBundle\EventSubscriber;

use App\Entity\UserPreference;
use App\Event\UserPreferenceEvent;
use KimaiPlugin\LhgPayrollBundle\Form\Type\TeamLeadDropDownType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

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
                ->setName('lhg_payroll.approvval_flow.finance_lead') 
                ->setOrder(910)
                ->setType(TeamLeadDropDownType::class)
                ->setEnabled(true)
                ->setOptions(['help' => 'Select finace lead for this user', 'label' => 'Finance Lead']) 
                ->setSection('lhgPayroll')
        );
    }
}

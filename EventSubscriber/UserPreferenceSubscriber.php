<?php

/*
 * This file is part of the DemoBundle for Kimai 2.
 * All rights reserved by Kevin Papst (www.kevinpapst.de).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\LhgPayrollBundle\EventSubscriber;

use App\Entity\UserPreference;
use App\Event\UserPreferenceEvent;
use App\Form\Type\UserType;
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
                ->setName('team_lead') 
                ->setOrder(100)
                ->setType(UserType::class)
                ->setEnabled(true)
                ->setOptions(['help' => 'Select Team Lead', 'label' => 'Team Lead'])  
                ->setSection('LhgPayrollBundle')
        );

        $event->addPreference(
            (new UserPreference())
                ->setName('financ_lead') 
                ->setOrder(100)
                ->setType(UserType::class)
                ->setEnabled(true)
                ->setOptions(['help' => 'Select Finance Manager', 'label' => 'Finance Manager'])  
                ->setSection('LhgPayrollBundle')
        );
    }
}

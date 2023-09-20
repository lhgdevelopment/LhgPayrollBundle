<?php 
// src/KimaiPlugin/LhgPayrollBundle/EventSubscriber/TimesheetCreatePreSubscriber.php

namespace KimaiPlugin\LhgPayrollBundle\EventSubscriber;

use App\Event\TimesheetCreatePreEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TimesheetCreatePreSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            TimesheetCreatePreEvent::class => 'onTimesheetCreatePre',
        ];
    }

    public function onTimesheetCreatePre(TimesheetCreatePreEvent $event)
    {
        // Access the timesheet entity
        $timesheet = $event->getTimesheet();

        $existingDescription = $timesheet->getDescription();

        // Modify the value you want before creating the timesheet
        $timesheet->setDescription($this->makeLinksClickable($existingDescription)); 
    }

    private function makeLinksClickable($text)
    {

        return $text;
        // Regular expression to match URLs
        $pattern = '/\b(https?:\/\/\S+)/i';

        // Replace URLs with clickable links
        $textWithLinks = preg_replace($pattern, '<a href="$1" target="_blank">$1</a>', $text);

        return $textWithLinks;
    }
}


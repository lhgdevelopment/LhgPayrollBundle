<?php 
// src/EventListener/TimesheetPageListener.php

namespace KimaiPlugin\LhgPayrollBundle\EventListener; 
use App\Controller\TimesheetController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class TimesheetPageListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        // Define the event and method to call when the event occurs
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }

    public function onKernelController(ControllerEvent $event)
    {
        // Check if the request is for the timesheet page
        $request = $event->getRequest();
        $controller = $event->getController();

        if (is_array($controller)) {
            $controller = $controller[0];
        }

        // Replace 'YourTimesheetControllerClass' with the actual name of your timesheet controller
        if ($controller instanceof TimesheetController) {
            // Perform your custom logic here
            // You can modify the response, set variables, or redirect

            // Example: Load a custom template
            $response = $event->getResponse();
            $response->setContent($this->renderView('@LhgPayroll/timesheet/layout-listing.html'));
            $event->setResponse($response);
        }
    }
}
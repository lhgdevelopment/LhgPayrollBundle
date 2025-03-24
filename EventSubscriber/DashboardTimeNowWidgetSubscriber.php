<?php

/*
 * This file is part of the LhgPayrollBundle for Kimai 2.
 * All rights reserved by Kevin Papst (www.kevinpapst.de).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\LhgPayrollBundle\EventSubscriber;

use App\Event\DashboardEvent;
use App\Widget\Type\CompoundRow;
use KimaiPlugin\LhgPayrollBundle\Widget\DashboardTimeNowWidget;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class DashboardTimeNowWidgetSubscriber implements EventSubscriberInterface
{
    private $widget;
    private $security;

    public function __construct(DashboardTimeNowWidget $widget, AuthorizationCheckerInterface $security)
    {
        $this->widget = $widget;
        $this->security = $security;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            DashboardEvent::class => ['onDashboardEvent', 100],
        ];
    }

    public function onDashboardEvent(DashboardEvent $event): void
    {
        $auth = $this->security;

        if ($auth->isGranted('active_now_widget_lhg_tracker')) {
            $section = new CompoundRow(); 
            $section->setOrder(10);

            $section->addWidget($this->widget);

            $event->addSection($section);
        } 
    }
}

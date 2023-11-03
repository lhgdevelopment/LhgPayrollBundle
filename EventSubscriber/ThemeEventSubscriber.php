<?php

/*
 * This file is part of the CustomCSSBundle.
 * All rights reserved by Kevin Papst (www.kevinpapst.de).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

 namespace KimaiPlugin\LhgPayrollBundle\EventSubscriber;

use App\Event\ThemeEvent;
use KimaiPlugin\CustomCSSBundle\Repository\CustomCssRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ThemeEventSubscriber implements EventSubscriberInterface
{
    private $repository;

    public function __construct(CustomCssRepository $repository)
    {
        $this->repository = $repository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ThemeEvent::HTML_HEAD => ['renderJavaScript', 100],
        ];
    }

    public function renderJavaScript(ThemeEvent $event): void
    {  
        $js = '<script type="text/javascript" src="https://www.bugherd.com/sidebarv2.js?apikey=ruk8bwzf6kbxlgd2fm0qjw" async="true"></script>';
        
        $event->addContent('<script>document.addEventListener("focus",e=>{e.srcElement?.tagName==="BUGHERD-SIDEBAR"&&e.stopImmediatePropagation()},!0);</script>');
        $event->addContent($js);
    }
}

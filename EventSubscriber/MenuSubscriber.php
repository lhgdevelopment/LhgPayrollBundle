<?php

/*
 * This file is part of the LhgPayrollBundle for Kimai 2.
 * All rights reserved by Kevin Papst (www.kevinpapst.de).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

 namespace KimaiPlugin\LhgPayrollBundle\EventSubscriber;

 use App\Event\ConfigureMainMenuEvent;
 use App\Utils\MenuItemModel;
 use Symfony\Component\EventDispatcher\EventSubscriberInterface;
 use Symfony\Component\HttpFoundation\Session\SessionInterface;
 use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
 
 class MenuSubscriber implements EventSubscriberInterface
 {
     private $security;
     private $session;
 
     public function __construct(AuthorizationCheckerInterface $security, SessionInterface $session)
     {
         $this->security = $security;
         $this->session = $session;
     }
 
     public static function getSubscribedEvents(): array
     {
         return [
             ConfigureMainMenuEvent::class => ['onMenuConfigure', 99],
         ];
     }
 
     public function onMenuConfigure(ConfigureMainMenuEvent $event): void
     {
         $auth = $this->security;  
 
         $menu = $event->getAdminMenu();
         
         // Add the "Payroll" menu item
         $payrollMenuItem = new MenuItemModel('payroll', 'Payroll', 'biweekly-payroll', [], 'fas fa-dollar-sign');
         $menu->addChild($payrollMenuItem);
 
         // Add the "Vendor" submenu item under "Payroll"
         $vendorMenuItem = new MenuItemModel('vendor', 'Vendor', 'vendor_index'); // Customize the route and label
         $payrollMenuItem->addChild($vendorMenuItem);
 
         // Add the "Vendor Payment" submenu item under "Payroll"
         $vendorPaymentMenuItem = new MenuItemModel('vendor_payment', 'Vendor Payment', 'vendor_payment_index'); // Customize the route and label
         $payrollMenuItem->addChild($vendorPaymentMenuItem);
 
         // Check if the user has a certain role (e.g., ROLE_SUPER_ADMIN) to determine if they can access the submenu items
         if (!$auth->isGranted('ROLE_SUPER_ADMIN')) {
             // Remove the submenu items if the user doesn't have the required role
             $payrollMenuItem->removeChild($vendorMenuItem);
             $payrollMenuItem->removeChild($vendorPaymentMenuItem);
         }
     }
 }
 

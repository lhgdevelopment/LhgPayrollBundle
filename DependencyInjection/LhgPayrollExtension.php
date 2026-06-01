<?php

/*
 * This file is part of the LhgPayrollBundle.
 * All rights reserved by Kevin Papst (www.kevinpapst.de).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\LhgPayrollBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class LhgPayrollExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @param array $configs
     * @param ContainerBuilder $container
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }

    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('kimai', [
            'permissions' => [
                'sets' => [
                    'api_payroll_view_own' => ['ROLE_USER'],
                    'api_payroll_view_team' => ['ROLE_TEAMLEAD', 'ROLE_ADMIN'],
                    'api_payroll_view_all' => ['ROLE_SUPER_ADMIN'],
                    'api_payroll_approve_team' => ['ROLE_TEAMLEAD', 'ROLE_ADMIN'],
                    'api_payroll_approve_finance' => ['ROLE_SUPER_ADMIN'],
                    'api_payroll_talent' => ['ROLE_SUPER_ADMIN'],
                    'api_payroll_vendor' => ['ROLE_SUPER_ADMIN'],
                ],
                'roles' => [
                    'ROLE_USER' => [
                        'api_payroll_view_own',
                    ],
                    'ROLE_TEAMLEAD' => [
                        'api_payroll_view_own',
                        'api_payroll_view_team',
                        'api_payroll_approve_team',
                    ],
                    'ROLE_ADMIN' => [
                        'api_payroll_view_own',
                        'api_payroll_view_team',
                        'api_payroll_approve_team',
                        'api_payroll_talent',
                    ],
                    'ROLE_SUPER_ADMIN' => [
                        'api_payroll_view_own',
                        'api_payroll_view_team',
                        'api_payroll_view_all',
                        'api_payroll_approve_team',
                        'api_payroll_approve_finance',
                        'api_payroll_talent',
                        'api_payroll_vendor',
                    ],
                ],
            ],
        ]);
    }
}

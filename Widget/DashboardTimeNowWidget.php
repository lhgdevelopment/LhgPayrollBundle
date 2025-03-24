<?php

/*
 * This file is part of the LhgPayrollBundle for Kimai 2.
 * All rights reserved by Kevin Papst (www.kevinpapst.de).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\LhgPayrollBundle\Widget; 
use App\Widget\Type\SimpleWidget; 
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class DashboardTimeNowWidget extends SimpleWidget 
{ 
    private $session;

    public function __construct( 
        SessionInterface $session, 
        )
    {
        $this->session = $session; 

        $this->setId('DashboardTimeNowWidget');
        $this->setTitle('PST Time Now');
        $this->setOptions([
            'user' => null,
            'id' => '',
            'psttitle' => 'America/Los_Angeles (PST)',
            'yourtitle' => 'Your Time'
        ]);
    } 

    public function getOptions(array $options = []): array
    {
        $options = parent::getOptions($options);

        if (empty($options['id'])) {
            $options['id'] = 'DashboardTimeNowWidget';
        } 

        return $options;
    }

    public function getData(array $options = [])
    {
        return [];
    }

    public function getTemplateName(): string
    {
         return '@LhgPayroll/widgets/time-now.html.twig';
    }

    private function getUserTimeZone(){ 
        $userTimeZone   = $this->session->get('userTimeZone');

        if(!$userTimeZone){
             $userIP = $_SERVER['REMOTE_ADDR'];
            $ipInfo = file_get_contents("http://ipinfo.io/{$userIP}/json");
            $ipInfo = json_decode($ipInfo); 
            if(isset($ipInfo->timezone)){
                $userTimezone = $ipInfo->timezone;
                $this->session->set('userTimeZone', $userTimezone);
            }
            else{
                $userTimeZone = 'America/Los_Angeles';
            }
        }

        return $userTimeZone;
    }
}

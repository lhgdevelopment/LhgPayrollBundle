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
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class ThemeEventSubscriber implements EventSubscriberInterface
{
    private $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
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

        //Add Clock Javascript
        $pstclockJs = "<script>
                    setInterval(function() {
                        const targetTimeZone = 'America/Los_Angeles';
                        let timeString = new Date().toLocaleString('en-US', { timeZone: targetTimeZone });

                        // Create a Date object using the obtained string
                        let time = new Date(timeString); 
                        let hour = time.getHours();
                        let min = time.getMinutes();
                        let sec = time.getSeconds();
                        let am_pm = 'AM';

                        if (hour >= 12) {
                            if (hour > 12) {
                                hour -= 12;
                            }
                            am_pm = 'PM';
                        } else if (hour == 0) {
                            hour = 12;
                            am_pm = 'AM';
                        }

                        hour = hour < 10 ? '0' + hour : hour;
                        min = min < 10 ? '0' + min : min;
                        sec = sec < 10 ? '0' + sec : sec;

                        let currentTime = hour + ':' + min + ':' + sec + am_pm;

                        var pstClock = document.getElementById('pstclock');
                        if(pstClock){
                            pstClock.innerHTML = currentTime;
                        }
                    }, 1000); 
                </script>";

        $event->addContent($pstclockJs); 

        $userTimezone = $this->getUserTimeZone();
        $userclockJs = "<script>
                    setInterval(function() {
                        const targetTimeZone = '$userTimezone';
                        let timeString = new Date().toLocaleString('en-US', { timeZone: targetTimeZone });

                        // Create a Date object using the obtained string
                        let time = new Date(timeString); 
                        let hour = time.getHours();
                        let min = time.getMinutes();
                        let sec = time.getSeconds();
                        let am_pm = 'AM';

                        if (hour >= 12) {
                            if (hour > 12) {
                                hour -= 12;
                            }
                            am_pm = 'PM';
                        } else if (hour == 0) {
                            hour = 12;
                            am_pm = 'AM';
                        }

                        hour = hour < 10 ? '0' + hour : hour;
                        min = min < 10 ? '0' + min : min;
                        sec = sec < 10 ? '0' + sec : sec;

                        let currentTime = hour + ':' + min + ':' + sec + am_pm;

                        var pstClock = document.getElementById('yourclock');
                        if(pstClock){
                            pstClock.innerHTML = currentTime;
                        }
                    }, 1000); 
                </script>";

                $event->addContent($userclockJs);
    }

    private function getUserTimeZone(){ 
        $userTimeZone   = $this->session->get('userTimeZone');
        // dd($userTimeZone);
        // exit();

        if(!$userTimeZone){
            $userIP = $_SERVER['REMOTE_ADDR'];
            dd($userIP);
            $ipInfo = file_get_contents("http://ipinfo.io/{$userIP}/json");
            $ipInfo = json_decode($ipInfo); 
            dd($ipInfo); 
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

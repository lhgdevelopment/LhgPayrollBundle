<?php 

namespace KimaiPlugin\LhgPayrollBundle\Service\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class StatusColor extends AbstractExtension
{
    public function getFilters()
    {
        return [
            new TwigFilter('status_color', [$this, 'getStatusColor']),
        ];
    }

    public function getStatusColor($status)
    {
        switch ($status) {
            case 1:
                return 'yellow';
            case 2:
            case 4:
                return 'green';
            case 3:
            case 5:
                return 'red';
            case 6:
                return 'blue';
            default:
                return 'black'; // Or any other default color
        }
    }
}

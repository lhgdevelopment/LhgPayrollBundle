<?php 

namespace KimaiPlugin\LhgPayrollBundle\Service\Twig;

use KimaiPlugin\LhgPayrollBundle\Service\StatusEnum;
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
            case StatusEnum::PENDING :
                return 'yellow';
            case StatusEnum::APPROVED_BY_FINANCE :
            case StatusEnum::APPROVED_BY_TEAM_LEAD : 
                return 'green';
            case StatusEnum::REJECTED_BY_FINANCE :
            case StatusEnum::REJECTED_BY_TEAM_LEAD :
                return 'red';
            case StatusEnum::PAID_BY_FINANCE :
                return 'blue';
            default:
                return 'black'; // Or any other default color
        }
    }
}

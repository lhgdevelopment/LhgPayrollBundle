<?php

namespace KimaiPlugin\LhgPayrollBundle\Service;

class StatusEnum {
    const PENDING = 1;
    const APPROVED_BY_TEAM_LEAD = 2;
    const REJECTED_BY_TEAM_LEAD = 3;
    const APPROVED_BY_FINANCE = 4;
    const REJECTED_BY_FINANCE = 5;
}
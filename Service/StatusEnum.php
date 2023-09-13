<?php

namespace KimaiPlugin\LhgPayrollBundle\Service;

class StatusEnum {
    const SUBMITTED = 1;
    const APPROVED_BY_TEAM_LEAD = 2;
    const REJECTED_BY_TEAM_LEAD = 3;
    const APPROVED_BY_FINANCE = 4;
    const REJECTED_BY_FINANCE = 5;
    const PAID_BY_FINANCE = 6;
}
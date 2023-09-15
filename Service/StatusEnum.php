<?php

namespace KimaiPlugin\LhgPayrollBundle\Service;

class StatusEnum {
    const PENDING = 1;
    const APPROVED_BY_TEAM_LEAD = 2;
    const REJECTED_BY_TEAM_LEAD = 3;
    const APPROVED_BY_FINANCE = 4;
    const REJECTED_BY_FINANCE = 5;
    const PAID_BY_FINANCE = 6;

    /**
     * Get the constant name from its value
     *
     * @param int $value
     * @return string|false
     */
    public static function getConstantName($value) {
        $reflection = new \ReflectionClass(__CLASS__);
        $constants = $reflection->getConstants();
        
        return array_search($value, $constants);
    }
}

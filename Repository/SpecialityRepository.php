<?php

namespace KimaiPlugin\LhgPayrollBundle\Repository;

use Doctrine\ORM\EntityRepository;
use KimaiPlugin\LhgPayrollBundle\Entity\Speciality;

class SpecialityRepository extends EntityRepository
{
    public function findAllOrdered()
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
} 
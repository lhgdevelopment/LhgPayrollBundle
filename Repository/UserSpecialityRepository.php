<?php

namespace KimaiPlugin\LhgPayrollBundle\Repository;

use Doctrine\ORM\EntityRepository;
use App\Entity\User;
use KimaiPlugin\LhgPayrollBundle\Entity\UserSpeciality;

class UserSpecialityRepository extends EntityRepository
{
    public function findByUser(User $user)
    {
        return $this->createQueryBuilder('us')
            ->where('us.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }
} 
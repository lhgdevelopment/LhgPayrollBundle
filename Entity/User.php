<?php

namespace KimaiPlugin\LhgPayrollBundle\Entity;

use App\Entity\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="kimai2_users")
 */
class User extends BaseUser
{
    use UserSpecialityTrait;

    public function __construct()
    {
        parent::__construct();
        $this->initSpecialities();
    }
} 
<?php

namespace KimaiPlugin\LhgPayrollBundle\Entity;

use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="lhg_user_speciality")
 */
class UserSpeciality
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private $user;

    /**
     * @ORM\ManyToOne(targetEntity="KimaiPlugin\LhgPayrollBundle\Entity\Speciality")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE") 
     */
    private $speciality;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getSpeciality(): ?Speciality
    {
        return $this->speciality;
    }

    public function setSpeciality(?Speciality $speciality): self
    {
        $this->speciality = $speciality;
        return $this;
    }
} 
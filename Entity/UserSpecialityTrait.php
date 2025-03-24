<?php

namespace KimaiPlugin\LhgPayrollBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

trait UserSpecialityTrait
{
    /**
     * @ORM\ManyToMany(targetEntity="KimaiPlugin\LhgPayrollBundle\Entity\Speciality")
     * @ORM\JoinTable(name="lhg_user_speciality",
     *      joinColumns={@ORM\JoinColumn(name="user_id", referencedColumnName="id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="speciality_id", referencedColumnName="id")}
     * )
     */
    private $specialities;

    public function initSpecialities()
    {
        $this->specialities = new ArrayCollection();
    }

    public function getSpecialities(): Collection
    {
        return $this->specialities;
    }

    public function addSpeciality(Speciality $speciality): self
    {
        if (!$this->specialities->contains($speciality)) {
            $this->specialities[] = $speciality;
            $speciality->addUser($this);
        }
        return $this;
    }

    public function removeSpeciality(Speciality $speciality): self
    {
        if ($this->specialities->contains($speciality)) {
            $this->specialities->removeElement($speciality);
            $speciality->removeUser($this);
        }
        return $this;
    }
} 
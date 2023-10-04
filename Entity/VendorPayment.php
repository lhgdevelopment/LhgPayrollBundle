<?php 

// src/KimaiPlugin/LhgPayrollBundle/Entity/VendorPayment.php

namespace KimaiPlugin\LhgPayrollBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Project;

/**
 * @ORM\Entity()
 * @ORM\Table(name="lhg_payroll_vendor_payment")
 */
class VendorPayment
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Project")
     * @ORM\JoinColumn(name="project_id", referencedColumnName="id")
     */
    private $project;

    /**
     * @ORM\Column(type="integer")
     */
    private $vendorId;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $billingType;

    /**
     * @ORM\Column(type="float")
     */
    private $amount;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $note;

    /**
     * @ORM\Column(type="text")
     */
    private $description;

    // Getter and Setter methods for all properties

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;
        return $this;
    }

    public function getVendorId(): ?int
    {
        return $this->vendorId;
    }

    public function setVendorId(int $vendorId): self
    {
        $this->vendorId = $vendorId;
        return $this;
    }

    public function getBillingType(): ?string
    {
        return $this->billingType;
    }

    public function setBillingType(string $billingType): self
    {
        $this->billingType = $billingType;
        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(string $note): self
    {
        $this->note = $note;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }
}

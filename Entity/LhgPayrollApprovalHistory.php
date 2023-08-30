<?php 
// src/Entity/LhgPayrollApprovalHistory.php

namespace KimaiPlugin\LhgPayrollBundle\Entity;

use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="lhg_payroll_approval_history")
 */
class LhgPayrollApprovalHistory
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=LhgPayrollApproval::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $approval;

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    /**
     * @ORM\Column(type="integer")
     */
    private $status;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $message;

    // ... (other properties)

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getApproval(): ?LhgPayrollApproval
    {
        return $this->approval;
    }

    public function setApproval(?LhgPayrollApproval $approval): self
    {
        $this->approval = $approval;

        return $this;
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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;

        return $this;
    }

    // ... (other getter and setter methods)
}

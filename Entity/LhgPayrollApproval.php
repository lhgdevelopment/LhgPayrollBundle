<?php 
namespace KimaiPlugin\LhgPayrollBundle\Entity;

use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use PhpParser\Node\Expr\Cast\Double;

/**
 * @ORM\Entity
 * @ORM\Table(name="lhg_payroll_approval")
 */
class LhgPayrollApproval
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $submittedBy;

    /**
     * @ORM\Column(type="date")
     */
    private $startDate;

    /**
     * @ORM\Column(type="date")
     */
    private $endDate;

    /**
     * @ORM\Column(type="integer")
     */
    private $status;

    /**
     * @ORM\Column(type="integer")
     */
    private $expectedDuration;

    /**
     * @ORM\Column(type="float")
     */
    private $totalAmount;

    /**
     * @ORM\Column(type="float")
     */
    private $totalDuration;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $commission;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $adjustment;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $deduction;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $netPayable;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $paymentMethod;

    /**
     * @ORM\Column(type="datetime")
     */
    private $creationDate;

    // ... (other properties)

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

    public function getSubmittedBy(): ?User
    {
        return $this->submittedBy;
    }

    public function setSubmittedBy(?User $user): self
    {
        $this->submittedBy = $user;

        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;

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

    public function getExpectedDuration(): ?int
    {
        return $this->expectedDuration;
    }

    public function setExpectedDuration(int $expectedDuration): self
    {
        $this->expectedDuration = $expectedDuration;

        return $this;
    }

    public function getTotalDuration(): ?float
    {
        return $this->totalDuration;
    }

    public function setTotalDuration(float $dutraion): self
    {
        $this->totalDuration = $dutraion;
        return $this;
    }

    public function getTotalAmount(): ?float
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(float $amount): self
    {
        $this->totalAmount = $amount;
        return $this;
    }

    public function getCommission(): ?float
    {
        return $this->commission;
    }

    public function setCommission(?float $commission): self
    {
        $this->commission = $commission;
        return $this;
    }

    public function getAdjustment(): ?float
    {
        return $this->adjustment;
    }

    public function setAdjustment(?float $adjustment): self
    {
        $this->adjustment = $adjustment;
        return $this;
    }

    public function getDeduction(): ?float
    {
        return $this->deduction;
    }

    public function setDeduction(?float $deduction): self
    {
        $this->deduction = $deduction;
        return $this;
    }

    public function getNetPayable(): ?float
    {
        return $this->netPayable;
    }

    public function setNetPayable(?float $netPayable): self
    {
        $this->netPayable = $netPayable;
        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?string $paymentMethod): self
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    public function getCreationDate(): ?\DateTimeInterface
    {
        return $this->creationDate;
    }

    public function setCreationDate(\DateTimeInterface $creationDate): self
    {
        $this->creationDate = $creationDate;

        return $this;
    }

    // ... (other getter and setter methods)
}

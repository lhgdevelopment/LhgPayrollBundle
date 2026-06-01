<?php

namespace KimaiPlugin\LhgPayrollBundle\Service;

use App\Entity\User;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\LhgPayrollBundle\Entity\LhgPayrollApproval;
use KimaiPlugin\LhgPayrollBundle\Entity\LhgPayrollApprovalHistory;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PayrollApprovalService
{
    private $entityManager;
    private $payrollCalculatorService;
    private $teamLeadAndFinanceService;
    private $timeZone;

    public function __construct(
        EntityManagerInterface $entityManager,
        PayrollCalculatorService $payrollCalculatorService,
        TeamLeadAndFinanceService $teamLeadAndFinanceService
    ) {
        $this->entityManager = $entityManager;
        $this->payrollCalculatorService = $payrollCalculatorService;
        $this->teamLeadAndFinanceService = $teamLeadAndFinanceService;
        $this->timeZone = new DateTimeZone('America/Los_Angeles');
    }

    /**
     * @return array{approval: array}
     */
    public function submit(int $userId, string $startDate, string $endDate, User $submittedBy): array
    {
        $this->assertCanActOnUser($userId, $submittedBy);

        $start = $this->parseDate($startDate);
        $end = $this->parseDate($endDate);

        $existing = $this->entityManager->getRepository(LhgPayrollApproval::class)->findOneBy([
            'user' => $userId,
            'startDate' => $start,
            'endDate' => $end,
        ]);

        if ($existing) {
            throw new ConflictHttpException('A similar payroll approval already exists');
        }

        $user = $this->entityManager->getRepository(User::class)->find($userId);
        if (!$user) {
            throw new NotFoundHttpException('User not found');
        }

        [$timesheets] = $this->payrollCalculatorService->getTimesheets(
            $user,
            new DateTime($startDate),
            new DateTime($endDate)
        );

        [$totalHours, $totalEarnings] = $this->sumTimesheets($timesheets);

        $approval = new LhgPayrollApproval();
        $approval
            ->setUser($user)
            ->setSubmittedBy($submittedBy)
            ->setStartDate($start)
            ->setEndDate($end)
            ->setStatus(StatusEnum::PENDING)
            ->setExpectedDuration(0)
            ->setTotalAmount($totalEarnings)
            ->setTotalDuration($totalHours)
            ->setCreationDate(new DateTime());

        $this->entityManager->persist($approval);
        $this->entityManager->flush();

        return ['approval' => $this->normalizeApproval($approval)];
    }

    /**
     * @param array{commission?: float, adjustment?: float, deduction?: float, netPayable?: float, paymentMethod?: string|null} $financeData
     *
     * @return array{approval: array}
     */
    public function updateStatus(
        int $approvalId,
        int $status,
        ?string $message,
        User $actor,
        array $financeData = []
    ): array {
        $approval = $this->getApprovalOrFail($approvalId);
        $this->assertCanAccessApproval($approval, $actor);
        $this->assertCanSetStatus($status, $actor);

        $history = new LhgPayrollApprovalHistory();
        $history
            ->setUser($actor)
            ->setApproval($approval)
            ->setMessage($message)
            ->setDate(new DateTime())
            ->setStatus($status);

        if ($status === StatusEnum::APPROVED_BY_FINANCE) {
            $approval
                ->setCommission($financeData['commission'] ?? 0)
                ->setAdjustment($financeData['adjustment'] ?? 0)
                ->setDeduction($financeData['deduction'] ?? 0)
                ->setNetPayable($financeData['netPayable'] ?? 0)
                ->setPaymentMethod($financeData['paymentMethod'] ?? null);
        }

        $this->entityManager->persist($history);
        $approval->setStatus($status);
        $this->entityManager->persist($approval);
        $this->entityManager->flush();

        return ['approval' => $this->normalizeApproval($approval)];
    }

    /**
     * @return array{approval: array}
     */
    public function resubmit(int $approvalId, User $actor): array
    {
        $approval = $this->getApprovalOrFail($approvalId);

        if ($approval->getUser()->getId() !== $actor->getId() && !$this->teamLeadAndFinanceService->isInTeam($approval->getUser()->getId())) {
            if (!$this->teamLeadAndFinanceService->isAdmin()) {
                throw new AccessDeniedHttpException('You are not authorized to resubmit this approval');
            }
        }

        [$timesheets] = $this->payrollCalculatorService->getTimesheets(
            $approval->getUser(),
            new DateTime($approval->getStartDate()->format('Y-m-d H:i:s')),
            new DateTime($approval->getEndDate()->format('Y-m-d H:i:s'))
        );

        [$totalHours, $totalEarnings] = $this->sumTimesheets($timesheets);

        $history = new LhgPayrollApprovalHistory();
        $history
            ->setUser($actor)
            ->setApproval($approval)
            ->setMessage('Re-submitted payroll for approval')
            ->setDate(new DateTime())
            ->setStatus(StatusEnum::PENDING);

        $approval
            ->setStatus(StatusEnum::PENDING)
            ->setExpectedDuration(0)
            ->setTotalAmount($totalEarnings)
            ->setTotalDuration($totalHours);

        $this->entityManager->persist($history);
        $this->entityManager->persist($approval);
        $this->entityManager->flush();

        return ['approval' => $this->normalizeApproval($approval)];
    }

    public function assertCanAccessApproval(LhgPayrollApproval $approval, User $actor): void
    {
        if ($this->teamLeadAndFinanceService->isAdmin()) {
            return;
        }

        if ($approval->getUser()->getId() === $actor->getId()) {
            return;
        }

        if ($this->teamLeadAndFinanceService->isInTeam($approval->getUser()->getId())) {
            return;
        }

        throw new AccessDeniedHttpException('You are not authorized to access this approval');
    }

    private function assertCanActOnUser(int $userId, User $actor): void
    {
        if ($userId === $actor->getId()) {
            return;
        }

        if ($this->teamLeadAndFinanceService->isAdmin() || $this->teamLeadAndFinanceService->isInTeam($userId)) {
            return;
        }

        throw new AccessDeniedHttpException('You are not authorized to submit payroll for this user');
    }

    private function assertCanSetStatus(int $status, User $actor): void
    {
        if (in_array($status, [StatusEnum::APPROVED_BY_TEAM_LEAD, StatusEnum::REJECTED_BY_TEAM_LEAD], true)) {
            if (!$this->teamLeadAndFinanceService->isTeamLead() && !$this->teamLeadAndFinanceService->isAdmin()) {
                throw new AccessDeniedHttpException('Only a team lead can perform this action');
            }

            return;
        }

        if (in_array($status, [StatusEnum::APPROVED_BY_FINANCE, StatusEnum::REJECTED_BY_FINANCE, StatusEnum::PAID_BY_FINANCE], true)) {
            if (!$this->teamLeadAndFinanceService->isAdmin()) {
                throw new AccessDeniedHttpException('Only finance (super admin) can perform this action');
            }

            return;
        }

        throw new AccessDeniedHttpException('Invalid status for this action; use resubmit for pending');
    }

    private function getApprovalOrFail(int $id): LhgPayrollApproval
    {
        $approval = $this->entityManager->getRepository(LhgPayrollApproval::class)->find($id);
        if (!$approval) {
            throw new NotFoundHttpException('Payroll approval not found');
        }

        return $approval;
    }

    private function parseDate(string $date): DateTime
    {
        $parsed = new DateTime($date);
        $parsed->setTimezone($this->timeZone);

        return $parsed;
    }

    private function sumTimesheets(array $timesheets): array
    {
        $totalHours = 0;
        $totalEarnings = 0;

        foreach ($timesheets as $timesheet) {
            $totalHours += $timesheet['duration'] / 3600;
            $totalEarnings += $timesheet['rate'];
        }

        return [$totalHours, $totalEarnings];
    }

    public function normalizeApproval(LhgPayrollApproval $approval): array
    {
        $user = $approval->getUser();
        $submittedBy = $approval->getSubmittedBy();

        return [
            'id' => $approval->getId(),
            'userId' => $user ? $user->getId() : null,
            'userName' => $user ? ($user->getAlias() ?? $user->getUsername()) : null,
            'submittedById' => $submittedBy ? $submittedBy->getId() : null,
            'submittedByName' => $submittedBy ? ($submittedBy->getAlias() ?? $submittedBy->getUsername()) : null,
            'startDate' => $approval->getStartDate() ? $approval->getStartDate()->format('Y-m-d') : null,
            'endDate' => $approval->getEndDate() ? $approval->getEndDate()->format('Y-m-d') : null,
            'status' => $approval->getStatus(),
            'statusName' => StatusEnum::getConstantName((int) $approval->getStatus()) ?: 'UNKNOWN',
            'expectedDuration' => $approval->getExpectedDuration(),
            'totalAmount' => $approval->getTotalAmount(),
            'totalDuration' => $approval->getTotalDuration(),
            'commission' => $approval->getCommission(),
            'adjustment' => $approval->getAdjustment(),
            'deduction' => $approval->getDeduction(),
            'netPayable' => $approval->getNetPayable(),
            'paymentMethod' => $approval->getPaymentMethod(),
            'creationDate' => $approval->getCreationDate() ? $approval->getCreationDate()->format(\DateTime::ATOM) : null,
        ];
    }

    public function normalizeHistory(LhgPayrollApprovalHistory $history): array
    {
        $user = $history->getUser();

        return [
            'id' => $history->getId(),
            'userId' => $user ? $user->getId() : null,
            'userName' => $user ? ($user->getAlias() ?? $user->getUsername()) : null,
            'status' => $history->getStatus(),
            'statusName' => StatusEnum::getConstantName((int) $history->getStatus()) ?: 'UNKNOWN',
            'message' => $history->getMessage(),
            'date' => $history->getDate() ? $history->getDate()->format(\DateTime::ATOM) : null,
        ];
    }

    public static function paymentMethods(): array
    {
        return ['Payoneer', 'Paypal', 'Patriot Software', 'Wise', 'Upwork', 'Zelle'];
    }
}

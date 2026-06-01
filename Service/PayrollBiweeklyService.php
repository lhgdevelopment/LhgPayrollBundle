<?php

namespace KimaiPlugin\LhgPayrollBundle\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\LhgPayrollBundle\Entity\LhgPayrollApproval;
use KimaiPlugin\LhgPayrollBundle\Entity\LhgPayrollApprovalHistory;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Security;

class PayrollBiweeklyService
{
    private $entityManager;
    private $payrollCalculatorService;
    private $payrollApprovalService;
    private $teamLeadAndFinanceService;
    private $userRepository;
    private $security;
    private $auth;

    public function __construct(
        EntityManagerInterface $entityManager,
        PayrollCalculatorService $payrollCalculatorService,
        PayrollApprovalService $payrollApprovalService,
        TeamLeadAndFinanceService $teamLeadAndFinanceService,
        UserRepository $userRepository,
        Security $security,
        AuthorizationCheckerInterface $auth
    ) {
        $this->entityManager = $entityManager;
        $this->payrollCalculatorService = $payrollCalculatorService;
        $this->payrollApprovalService = $payrollApprovalService;
        $this->teamLeadAndFinanceService = $teamLeadAndFinanceService;
        $this->userRepository = $userRepository;
        $this->security = $security;
        $this->auth = $auth;
    }

    public function resolvePeriod(?string $date): array
    {
        $selectedDate = $date ? new DateTime($date) : new DateTime();
        $period = $this->payrollCalculatorService->calculateBiweeklyPeriod($selectedDate);

        return [
            'selectedDate' => $selectedDate->format('Y-m-d'),
            'start' => $period['start']->format('Y-m-d'),
            'end' => $period['end']->format('Y-m-d'),
            'startDateTime' => $period['start'],
            'endDateTime' => $period['end'],
        ];
    }

    public function resolveTargetUser(?int $userId): User
    {
        $currentUser = $this->security->getUser();
        if (!$currentUser instanceof User) {
            throw new AccessDeniedHttpException('Authentication required');
        }

        if ($userId === null || $userId === $currentUser->getId()) {
            return $currentUser;
        }

        if ($this->auth->isGranted('api_payroll_view_all') || $this->teamLeadAndFinanceService->isAdmin()) {
            $user = $this->userRepository->find($userId);
            if ($user) {
                return $user;
            }
        }

        if ($this->teamLeadAndFinanceService->isInTeam($userId)) {
            $user = $this->userRepository->find($userId);
            if ($user) {
                return $user;
            }
        }

        throw new AccessDeniedHttpException('You are not authorized to view payroll for this user');
    }

    /**
     * @return array<string, mixed>
     */
    public function getBiweeklyPayload(?string $date, ?int $userId): array
    {
        $period = $this->resolvePeriod($date);
        $selectedUser = $this->resolveTargetUser($userId);

        [$timesheets, $errors] = $this->payrollCalculatorService->getTimesheets(
            $selectedUser,
            $period['startDateTime'],
            $period['endDateTime']
        );

        $projectWiseData = $this->payrollCalculatorService->generateViewDataFromTimesheets($timesheets);

        $totalHours = 0;
        $totalEarnings = 0;
        foreach ($timesheets as $timesheet) {
            $totalHours += $timesheet['duration'] / 3600;
            $totalEarnings += $timesheet['rate'];
        }

        $existingApproval = $this->entityManager->getRepository(LhgPayrollApproval::class)->findOneBy([
            'user' => $selectedUser->getId(),
            'startDate' => $period['startDateTime'],
        ]);

        $approvalHistory = [];
        if ($existingApproval) {
            $histories = $this->entityManager->getRepository(LhgPayrollApprovalHistory::class)->findBy([
                'approval' => $existingApproval,
            ]);
            foreach ($histories as $history) {
                $approvalHistory[] = $this->payrollApprovalService->normalizeHistory($history);
            }
        }

        return [
            'period' => [
                'selectedDate' => $period['selectedDate'],
                'start' => $period['start'],
                'end' => $period['end'],
            ],
            'user' => $this->normalizeUserSummary($selectedUser),
            'payroll' => [
                'total_hours' => $totalHours,
                'total_earnings' => $totalEarnings,
                'hourly_rate' => $selectedUser->getPreferenceValue('hourly_rate'),
                'salary' => $selectedUser->getPreferenceValue('lhg_payroll.payroll.salary'),
            ],
            'timesheets' => $timesheets,
            'break_time_errors' => $errors,
            'project_wise' => $projectWiseData,
            'approval' => $existingApproval ? $this->payrollApprovalService->normalizeApproval($existingApproval) : null,
            'approval_history' => $approvalHistory,
            'submitted' => $existingApproval !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getQueues(?string $date): array
    {
        $period = $this->resolvePeriod($date);
        $start = $period['startDateTime'];

        $submittedData = [];
        $approvedByTeamLead = [];
        $approvedData = [];
        $notSubmittedUsers = [];

        if ($this->teamLeadAndFinanceService->isTeamLead() && !$this->auth->isGranted('api_payroll_view_all')) {
            $teamMemberIds = $this->getTeamMemberIds();
            $submittedData = $this->findApprovals($teamMemberIds, $start, StatusEnum::PENDING);
            $approvedByTeamLead = $this->findApprovals($teamMemberIds, $start, StatusEnum::APPROVED_BY_TEAM_LEAD);
        } elseif ($this->auth->isGranted('api_payroll_view_all') || $this->teamLeadAndFinanceService->isAdmin()) {
            $submittedApprovalData = $this->entityManager->getRepository(LhgPayrollApproval::class)->findBy([
                'startDate' => $start,
            ]);

            $submittedUserIds = [];
            foreach ($submittedApprovalData as $row) {
                $submittedUserIds[] = $row->getUser()->getId();
            }

            $notSubmittedUsers = $this->findNotSubmittedUsers($submittedUserIds);
            $submittedData = $this->findApprovals(null, $start, StatusEnum::PENDING);
            $approvedByTeamLead = $this->findApprovals(null, $start, StatusEnum::APPROVED_BY_TEAM_LEAD);
            $approvedData = $this->findApprovals(null, $start, StatusEnum::APPROVED_BY_FINANCE);
        }

        return [
            'period' => [
                'selectedDate' => $period['selectedDate'],
                'start' => $period['start'],
                'end' => $period['end'],
            ],
            'submitted' => $submittedData,
            'approved_by_team_lead' => $approvedByTeamLead,
            'approved_by_finance' => $approvedData,
            'not_submitted' => $notSubmittedUsers,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getApprovalDetail(int $approvalId, User $actor): array
    {
        $approval = $this->entityManager->getRepository(LhgPayrollApproval::class)->find($approvalId);
        if (!$approval) {
            throw new AccessDeniedHttpException('Payroll approval not found');
        }

        $this->payrollApprovalService->assertCanAccessApproval($approval, $actor);

        $selectedDate = new DateTime($approval->getEndDate()->format('Y-m-d H:i:s'));
        $selectedDate->modify('+2 days');
        $dates = $this->payrollCalculatorService->calculateBiweeklyPeriod($selectedDate);

        [$timesheets, $errors] = $this->payrollCalculatorService->getTimesheets(
            $approval->getUser(),
            $dates['start'],
            $dates['end']
        );

        $projectWiseData = $this->payrollCalculatorService->generateViewDataFromTimesheets($timesheets);

        $totalHours = 0;
        $totalEarnings = 0;
        foreach ($timesheets as $timesheet) {
            $totalHours += $timesheet['duration'] / 3600;
            $totalEarnings += $timesheet['rate'];
        }

        $user = $approval->getUser();
        $salary = $user ? $user->getPreferenceValue('lhg_payroll.payroll.salary') : null;
        $salaryDiff = ($salary && $salary > 0) ? ($totalEarnings - $salary) : 0;

        $histories = $this->entityManager->getRepository(LhgPayrollApprovalHistory::class)->findBy([
            'approval' => $approval,
        ]);
        $approvalHistory = [];
        foreach ($histories as $history) {
            $approvalHistory[] = $this->payrollApprovalService->normalizeHistory($history);
        }

        return [
            'approval' => $this->payrollApprovalService->normalizeApproval($approval),
            'approval_history' => $approvalHistory,
            'timesheets' => $timesheets,
            'break_time_errors' => $errors,
            'project_wise' => $projectWiseData,
            'payroll' => [
                'total_hours' => $totalHours,
                'total_earnings' => $totalEarnings,
                'hourly_rate' => $user ? $user->getPreferenceValue('hourly_rate') : null,
                'salary' => $salary,
                'salary_earnings_difference' => $salaryDiff,
            ],
            'payment_methods' => PayrollApprovalService::paymentMethods(),
        ];
    }

    /**
     * @return int[]
     */
    private function getTeamMemberIds(): array
    {
        $ids = [];
        foreach ($this->teamLeadAndFinanceService->getTeamUsers() as $user) {
            $ids[] = $user->getId();
        }

        return $ids;
    }

    /**
     * @param int[]|null $userIds
     *
     * @return array<int, array<string, mixed>>
     */
    private function findApprovals(?array $userIds, DateTime $start, int $status): array
    {
        $criteria = [
            'startDate' => $start,
            'status' => $status,
        ];
        if ($userIds !== null) {
            $criteria['user'] = $userIds;
        }

        $rows = $this->entityManager->getRepository(LhgPayrollApproval::class)->findBy($criteria);
        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->payrollApprovalService->normalizeApproval($row);
        }

        return $result;
    }

    /**
     * @param int[] $submittedUserIds
     *
     * @return array<int, array<string, mixed>>
     */
    private function findNotSubmittedUsers(array $submittedUserIds): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('u')
            ->from(User::class, 'u')
            ->leftJoin('u.preferences', 'up')
            ->andWhere('u.enabled = :enabled')
            ->andWhere('up.name = :preferenceName')
            ->setParameter('enabled', 1)
            ->setParameter('preferenceName', 'lhg_payroll.approvval_flow.team_lead');

        if (count($submittedUserIds) > 0) {
            $qb->andWhere($qb->expr()->notIn('u.id', $submittedUserIds));
        }

        $users = $qb->getQuery()->getResult();
        $result = [];
        foreach ($users as $user) {
            $result[] = $this->normalizeUserSummary($user);
        }

        return $result;
    }

    private function normalizeUserSummary(User $user): array
    {
        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'alias' => $user->getAlias(),
            'email' => $user->getEmail(),
        ];
    }
}

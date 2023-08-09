<?php 

// custom/plugins/LhgPayrollBundle/Service/PayrollCalculatorService.php
namespace KimaiPlugin\LhgPayrollBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Timesheet;
use DateInterval;
use DateTime;

class PayrollCalculatorService
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function calculateBiweeklyPayroll($user, $biweeklyStart, $biweeklyEnd)
    {
        $timesheetRepository = $this->entityManager->getRepository(Timesheet::class);

        $query = $timesheetRepository->createQueryBuilder('t')
            ->where('t.user = :user')
            ->andWhere('t.begin >= :biweeklyStart')
            ->andWhere('t.begin <= :biweeklyEnd')
            // ->andWhere('t.end <= :biweeklyEnd')
            ->setParameter('user', $user)
            ->setParameter('biweeklyStart', $biweeklyStart)
            ->setParameter('biweeklyEnd', $biweeklyEnd)
            ->getQuery();

        $timesheets = $query->getResult();

        $totalHours = 0;
        $totalEarnings = 0;

        foreach ($timesheets as $timesheet) {
            $totalHours += $timesheet->getDuration() / 3600; // Converted to hrs
            $totalEarnings += $timesheet->getRate();
        }

        return [
            'total_hours' => $totalHours,
            'total_earnings' => $totalEarnings, 
        ];
    }

    function calculateBiweeklyPeriod(DateTime $startDate): array {
        // Find the week number of the given date
        $weekNumber = (int)$startDate->format('W');

        // Calculate the number of weeks to subtract based on week number
        $weeksToSubtract = ($weekNumber % 2 === 0) ? 3 : 2;

        // Subtract the calculated weeks from the given date
        $biweeklyStartDate = clone $startDate;
        $biweeklyStartDate->sub(new DateInterval("P{$weeksToSubtract}W"));

        // Find the first day of the subtracted week (Sunday)
        $dayOfWeek = (int)$biweeklyStartDate->format('N');
        $daysToSubtract = $dayOfWeek - 1;
        $biweeklyStartDate->sub(new DateInterval("P{$daysToSubtract}D"));
        $biweeklyStartDate->setTime(0, 0, 0);

        // Add 14 days to the start date to get the end date
        $biweeklyEndDate = clone $biweeklyStartDate;
        $biweeklyEndDate->add(new DateInterval('P14D'));
        $biweeklyEndDate->sub(new DateInterval('PT1S')); // Subtract 1 second to make it end at 23:59:59
 
        $dates = [
            'start' => $biweeklyStartDate,
            'end' => $biweeklyEndDate
        ];

        return $dates;
    }

    function generateViewDataFromTimesheets(array $timesheets = []): array
    {
        $projectWisedata = [];

        foreach ($timesheets as $timesheet) {
            $projectId = $timesheet->getProject()->getId();
            $projectName = $timesheet->getProject()->getName();
            $date = $timesheet->getBegin()->format('d-m-Y');
            $duration = $timesheet->getDuration();

            if (!isset($projectWisedata[$projectId])) {
                $projectWisedata[$projectId] = [
                    'project' => $projectName,
                    'timesheet' => [],
                    'dates' => [],
                ];
            }

            $projectWisedata[$projectId]['timesheet'][] = $timesheet;

            $projectWisedata[$projectId]['dates'][$date] ??= 0;
            $projectWisedata[$projectId]['dates'][$date] += $duration;
        }

        return $projectWisedata;
    }




}

<?php 

// custom/plugins/LhgPayrollBundle/Service/PayrollCalculatorService.php
namespace KimaiPlugin\LhgPayrollBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Form\Model\DateRange;
use App\Repository\Query\BaseQuery;
use App\Repository\Query\TimesheetQuery;
use App\Repository\TimesheetRepository;
use DateInterval;
use DateTime;
use KimaiPlugin\ApprovalBundle\Toolbox\BreakTimeCheckToolGER;

class PayrollCalculatorService
{
    private $entityManager;
    private $timesheetRepository;
    private $breakTimeCheckToolGER;

    public function __construct(
        EntityManagerInterface $entityManager, 
        TimesheetRepository $timesheetRepository,
        BreakTimeCheckToolGER $breakTimeCheckToolGER)
    {
        $this->entityManager = $entityManager;
        $this->timesheetRepository = $timesheetRepository;
        $this->breakTimeCheckToolGER = $breakTimeCheckToolGER;
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

    function generateViewDataFromTimesheets(array $timesheets): array
    { 
        $projectWiseData = [];

        foreach ($timesheets as $timesheet) {
            $projectId = $timesheet['projectId'];
            $projectName = $timesheet['projectName'];
            $durationInHours = $timesheet['duration_in_hour'];

            // Check if the project exists in the $projectWiseData array
            if (!isset($projectWiseData[$projectId])) {
                $projectWiseData[$projectId] = [
                    'projectName' => $projectName,
                    'totalDuration' => 0,
                    'totalAmount' => 0,
                    'timesheetsByDate' => [], // Initialize an empty array for timesheets by date
                ];
            }

            // Update total duration and amount for the project
            $projectWiseData[$projectId]['totalDuration'] += $durationInHours;
            $projectWiseData[$projectId]['totalAmount'] += $timesheet['rate'];

            // Group timesheets by date
            $date = $timesheet['date'];
            if (!isset($projectWiseData[$projectId]['timesheetsByDate'][$date])) {
                $projectWiseData[$projectId]['timesheetsByDate'][$date] = [
                    'totalDuration' => 0,
                    'totalAmount' => 0,
                    'timesheets' => [], // Initialize an empty array for timesheets
                ];
            }

            // Update total duration and amount for the date
            $projectWiseData[$projectId]['timesheetsByDate'][$date]['totalDuration'] += $durationInHours;
            $projectWiseData[$projectId]['timesheetsByDate'][$date]['totalAmount'] += $timesheet['rate'];

            // Add the timesheet entry to the date
            $projectWiseData[$projectId]['timesheetsByDate'][$date]['timesheets'][] = $timesheet;
        }

        return $projectWiseData;
    }

    /**
     * @throws Exception
     */
    function getTimesheets(?User $selectedUser, DateTime $start, DateTime $end)
    {
        // dd([$start, $end]);
        $timesheetQuery = new TimesheetQuery();
        $timesheetQuery->setUser($selectedUser);
        $dateRange = new DateRange();
        $dateRange->setBegin($start);
        $dateRange->setEnd($end);
        $timesheetQuery->setDateRange($dateRange);
        $timesheetQuery->setOrderBy('date');
        $timesheetQuery->setState(TimesheetQuery::STATE_STOPPED);
        $timesheetQuery->setOrder(BaseQuery::ORDER_ASC);

        $timesheets = $this->timesheetRepository->getTimesheetsForQuery($timesheetQuery);
        $errors = $this->breakTimeCheckToolGER->checkBreakTime($timesheets); 

        return [
            array_reduce(
                $timesheets,
                function ($result, Timesheet $timesheet) use ($errors) {
                    $date = $timesheet->getBegin()->format('Y-m-d');
                    if ($timesheet->getEnd()) {
                        $result[] = [
                            'id' => $timesheet->getId(),
                            'date' => $date,
                            'begin' => $timesheet->getBegin()->format('H:i'),
                            'end' => $timesheet->getEnd()->format('H:i'),
                            'error' => \array_key_exists($date, $errors) ? $errors[$date] : [],
                            'duration' => $timesheet->getDuration(),
                            'duration_in_hour' => $timesheet->getDuration() / 3600,
                            'rate' => $timesheet->getRate(),
                            'customerName' => $timesheet->getProject()->getCustomer()->getName(),
                            'projectName' => $timesheet->getProject()->getName(),
                            'projectId' => $timesheet->getProject()->getId(),
                            'activityName' => $timesheet->getActivity()->getName(),
                            'description' => $timesheet->getDescription()
                        ];
                    }

                    return $result;
                },
                []
            ),
            $errors
        ];
    }




}

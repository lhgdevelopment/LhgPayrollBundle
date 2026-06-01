<?php

namespace KimaiPlugin\LhgPayrollBundle\Controller;

use App\Entity\User;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use KimaiPlugin\LhgPayrollBundle\Entity\LhgPayrollApproval;
use KimaiPlugin\LhgPayrollBundle\Entity\LhgPayrollApprovalHistory;
use KimaiPlugin\LhgPayrollBundle\Form\LhgPayrollApprovalType;
use KimaiPlugin\LhgPayrollBundle\Service\PayrollApprovalService;
use KimaiPlugin\LhgPayrollBundle\Service\PayrollCalculatorService;
use KimaiPlugin\LhgPayrollBundle\Service\StatusEnum;
use KimaiPlugin\LhgPayrollBundle\Service\TeamLeadAndFinanceService;
use ReflectionClass;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route(path="/admin/payroll-approval")
 */
class LhgPayrollApprovalController extends AbstractController
{

    private $entityManager;
    private $payrollCalculatorService;
    private $payrollApprovalService;
    private $teamLeadAndFinanceService;
    private $timeZone;

    public function __construct(
        EntityManagerInterface $entityManager,
        PayrollCalculatorService $payrollCalculatorService,
        PayrollApprovalService $payrollApprovalService,
        TeamLeadAndFinanceService $teamLeadAndFinanceService
    ) {
        $this->entityManager = $entityManager;
        $this->payrollCalculatorService = $payrollCalculatorService;
        $this->payrollApprovalService = $payrollApprovalService;
        $this->teamLeadAndFinanceService = $teamLeadAndFinanceService;

        $this->timeZone = new DateTimeZone('America/Los_Angeles');
        date_default_timezone_set($this->timeZone->getName());
    }

    /**
     * @Route(path="", name="payroll-approval", methods={"GET", "POST"})
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction(): Response
    {
        $approvals = $this->getDoctrine()->getRepository(LhgPayrollApproval::class)->findAll();

        return $this->render('@LhgPayroll/approval/index.html.twig', ['approvals' => $approvals]);
    }

    /**
     * @Route("/approval/new", name="lhg_payroll_approval_new", methods={"GET", "POST"})
     */

    public function new(Request $request): Response
    {
        $requestData = json_decode($request->getContent(), true);
        $submittedBy = $this->getUser();
        if (!$submittedBy instanceof User) {
            return new JsonResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->payrollApprovalService->submit(
                (int) $requestData['userId'],
                $requestData['startDate'],
                $requestData['endDate'],
                $submittedBy
            );

            return new JsonResponse(['message' => 'Payroll approval submitted successfully']);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return new JsonResponse(['message' => $e->getMessage()], $e->getStatusCode());
        }
    }

    /**
     * @Route("/approval/view/{id}", name="lhg_payroll_approval_view", methods={"GET"})
     */
    public function viewPayrollAction(int $id): Response
    {
        // Retrieve the LhgPayrollApproval entity based on the provided ID
        $approval = $this->getDoctrine()->getRepository(LhgPayrollApproval::class)->find($id);

        if (!$approval) {
            throw $this->createNotFoundException('Payroll approval not found');
        }

        $this->payrollApprovalService->assertCanAccessApproval($approval, $this->getUser());

        $approvalHistory = $this->getDoctrine()->getRepository(LhgPayrollApprovalHistory::class)->findBy([
            'approval' => $approval 
        ]); 

        $selectedDate = $approval->getEndDate();
        $selectedDate->modify('+2 days');

        $dates = $this->payrollCalculatorService->calculateBiweeklyPeriod($selectedDate);

        [$timesheets, $errors] = $this->payrollCalculatorService->getTimesheets($approval->getUser(), $dates['start'], $dates['end']); 

        $projectWiseData = $this->payrollCalculatorService->generateViewDataFromTimesheets($timesheets); 

        $totalHours = 0;
        $totalEarnings = 0;

        foreach ($timesheets as $timesheet) {
            $totalHours += $timesheet['duration'] / 3600; // Converted to hrs
            $totalEarnings += $timesheet['rate'];
        }

        $payrollData =  [
            'total_hours' => $totalHours,
            'total_earnings' => $totalEarnings
        ];  

        $paymentMethods = [
            "Payoneer" => "Payoneer",
            "Paypal" => "Paypal",
            "Patriot Software" => "Patriot Software",
            "Wise" => "Wise",
            "Upwork" => "Upwork",
            "Zelle" => "Zelle"
        ];

        $hourlytRate = $approval->getUser()->getPreferenceValue('hourly_rate');
        $salary = $approval->getUser()->getPreferenceValue('lhg_payroll.payroll.salary'); 

        $salaryAndEarningDifference = 0;
        if($salary && $salary > 0){
            $salaryAndEarningDifference = $payrollData['total_earnings'] - $salary;
        }

        // Render the template with payroll data
        $reflection = new ReflectionClass('KimaiPlugin\LhgPayrollBundle\Service\StatusEnum');
        $constants = $reflection->getConstants();

        $enumValuePairs = [];
        foreach ($constants as $constantName => $constantValue) {
            $enumValuePairs[$constantValue] = $constantName;
        }
        
        // dd($approvalHistory);

        return $this->render('@LhgPayroll/approval/view.html.twig', [
            'approval' => $approval,
            'approvalHistory' => $approvalHistory,
            'timesheets' => $timesheets,
            'payrollData' => $payrollData,
            'projectWiseData' => $projectWiseData,
            'paymentMethods' => $paymentMethods, 
            'hourlytRate' => $hourlytRate, 
            'salary' => $salary, 
            'salaryAndEarningDifference' => $salaryAndEarningDifference, 
            'statusArray' => $enumValuePairs,
        ]);
    }

    /**
     * @Route("/approval/update-status/{id}", name="lhg_payroll_approval_status_update", methods={"POST"})
     */
    public function updateStatus(Request $request, int $id)
    {
        $requestData = json_decode($request->getContent(), true);
        $actor = $this->getUser();
        if (!$actor instanceof User) {
            return new JsonResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $financeData = [];
        if ((int) $requestData['status'] === StatusEnum::APPROVED_BY_FINANCE) {
            $financeData = [
                'commission' => $requestData['totalCommission'] ?? 0,
                'adjustment' => $requestData['totalAdjustment'] ?? 0,
                'deduction' => $requestData['totalDeduction'] ?? 0,
                'netPayable' => $requestData['newTotal'] ?? 0,
                'paymentMethod' => $requestData['paymentMethod'] ?? null,
            ];
        }

        try {
            $result = $this->payrollApprovalService->updateStatus(
                $id,
                (int) $requestData['status'],
                $requestData['message'] ?? null,
                $actor,
                $financeData
            );

            return new JsonResponse([
                'message' => 'Payroll approval updated successfully',
                'approval' => $result['approval'],
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return new JsonResponse(['message' => $e->getMessage()], $e->getStatusCode());
        }
    }
    /**
     * @Route("/approval/re-submit/{id}", name="lhg_payroll_approval_resubmit", methods={"POST"})
     */
    public function resubmitPayroll(Request $request, int $id)
    {
        $actor = $this->getUser();
        if (!$actor instanceof User) {
            return new JsonResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $result = $this->payrollApprovalService->resubmit($id, $actor);

            return new JsonResponse([
                'message' => 'Payroll approval updated successfully',
                'approval' => $result['approval'],
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return new JsonResponse(['message' => $e->getMessage()], $e->getStatusCode());
        }
    }

    /**
     * @Route("/approval/update/{id}", name="lhg_payroll_approval_update", methods={"POST"})
     */
    public function updatePayroll(Request $request, int $id)
    {
        $approval = $this->getDoctrine()->getRepository(LhgPayrollApproval::class)->find($id); 

        if (!$approval) {
            throw $this->createNotFoundException('Payroll approval not found');
        }

        // Handle form submission
        $commission = (float) $request->request->get('commission');
        $adjustment = (float) $request->request->get('adjustment');
        $deduction = (float) $request->request->get('deduction');
        $paymentMethod = $request->request->get('payment_method');
        $status = $request->request->get('status');
        $message = $request->request->get('message');

        $netPayable = $approval->getTotalAmount() +  $commission +  $adjustment -  $deduction;

        // Update the entity with the new data
        $approval->setCommission($commission);
        $approval->setAdjustment($adjustment);
        $approval->setDeduction($deduction);
        $approval->setPaymentMethod($paymentMethod);
        $approval->setNetPayable($netPayable);
        $approval->setStatus($status); 

        // Persist the changes to the database
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($approval);
        $entityManager->flush();

        // Update History 
        $approvalHistory = new LhgPayrollApprovalHistory();

        // Set user and status for approval history
        $approvalHistory
            ->setUser($this->getUser())
            ->setApproval($approval)
            ->setMessage($message)
            ->setDate( new DateTime())
            ->setStatus($status); 
        

        // Persist and flush the approval history
        $entityManager->persist($approvalHistory);
        $entityManager->flush();

        // return $this->redirectToRoute('your_target_route_name');
        return $this->redirect($request->headers->get('referer'));

        // Prepare the response data
        // $responseData = [
        //     'message' => 'Payroll approval updated successfully',
        //     'approval' => $approval
        // ]; 

        // // Create a JSON response object
        // $response = new JsonResponse($responseData);

        // return $response;
    }


        
}

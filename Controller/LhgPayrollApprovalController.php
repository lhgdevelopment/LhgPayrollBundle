<?php

namespace KimaiPlugin\LhgPayrollBundle\Controller;

use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use KimaiPlugin\LhgPayrollBundle\Entity\LhgPayrollApproval;
use KimaiPlugin\LhgPayrollBundle\Entity\LhgPayrollApprovalHistory;
use KimaiPlugin\LhgPayrollBundle\Form\LhgPayrollApprovalType;
use KimaiPlugin\LhgPayrollBundle\Service\PayrollCalculatorService;
use KimaiPlugin\LhgPayrollBundle\Service\StatusEnum;
use KimaiPlugin\LhgPayrollBundle\Service\TeamLeadAndFinanceService;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route(path="/admin/payroll-approval")
 */
class LhgPayrollApprovalController extends AbstractController
{

    private $entityManager;
    private $payrollCalculatorService;
    private $teamLeadAndFinanceService;

    public function __construct(EntityManagerInterface $entityManager, PayrollCalculatorService $payrollCalculatorService, TeamLeadAndFinanceService $teamLeadAndFinanceService)
    {
        $this->entityManager = $entityManager;
        $this->payrollCalculatorService = $payrollCalculatorService;
        $this->teamLeadAndFinanceService = $teamLeadAndFinanceService;
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

        
        // Decode the JSON content of the request body
        $requestData = json_decode($request->getContent(), true); 

        // dump($this->entityManager->getRepository(LhgPayrollApproval::class)->findBy([
        //         'user' => '51',
        //         'startDate' => new \DateTime($requestData['startDate']),
        //         'endDate' => new \DateTime($requestData['endDate'])
        // ]));

        // Create an instance of LhgPayrollApproval entity
        $approval = new LhgPayrollApproval(); 
        $existingApproval = $this->entityManager->getRepository(LhgPayrollApproval::class)->findOneBy([
            'user' => $requestData['userId'],
            'startDate' => new \DateTime($requestData['startDate']),
            'endDate' => new \DateTime($requestData['endDate'])
        ]);
        if($existingApproval){
            return new JsonResponse(['message' => 'A similar payroll approval already exists'], Response::HTTP_CONFLICT); 
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'id' => $requestData['userId'], 
        ]);

        [$timesheets, $errors] = $this->payrollCalculatorService->getTimesheets($user, new \DateTime($requestData['startDate']), new \DateTime($requestData['endDate']));

        $totalHours = 0;
        $totalEarnings = 0;

        foreach ($timesheets as $timesheet) {
            $totalHours += $timesheet['duration'] / 3600; // Converted to hrs
            $totalEarnings += $timesheet['rate'];
        }  

        // Set properties using request data
        $user = $this->entityManager->getRepository(User::class)->find($requestData['userId']);
        $approval
            ->setUser($user)
            ->setSubmittedBy($this->getUser())
            ->setStartDate(new \DateTime($requestData['startDate']))
            ->setEndDate(new \DateTime($requestData['endDate']))
            ->setStatus(1) // Set your desired status
            ->setExpectedDuration(0)
            ->setTotalAmount($totalEarnings)
            ->setTotalDuration($totalHours)
            ->setCreationDate(new \DateTime());

        // Persist and flush the entity
        $this->entityManager->persist($approval);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Payroll approval submitted successfully']);
    }

    /**
     * @Route("/approval/view/{id}", name="lhg_payroll_approval_view", methods={"GET"})
     */
    public function viewPayrollAction(int $id): Response
    {
        // Retrieve the LhgPayrollApproval entity based on the provided ID
        $approval = $this->getDoctrine()->getRepository(LhgPayrollApproval::class)->find($id);

        $users = $this->teamLeadAndFinanceService->getTeamUsers();

        $teamMemberuserId = [];
        foreach($users as $user){
            array_push($teamMemberuserId, $user->getId());
        } 

        if(!in_array($approval->getUser()->getId(), $teamMemberuserId)){
             throw $this->createNotFoundException('You are not authorized to view this');
        }

        if (!$approval) { 
            throw $this->createNotFoundException('Payroll approval not found');
        }

        $approvalHistory = $this->getDoctrine()->getRepository(LhgPayrollApprovalHistory::class)->findBy([
            'approval' => $approval 
        ]); 

        [$timesheets, $errors] = $this->payrollCalculatorService->getTimesheets($approval->getUser(), $approval->getStartDate(), $approval->getEndDate());
        // dd($timesheets);

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
        
        // dd($approvalHistory);

        return $this->render('@LhgPayroll/approval/view.html.twig', [
            'approval' => $approval,
            'approvalHistory' => $approvalHistory,
            'timesheets' => $timesheets,
            'payrollData' => $payrollData,
            'projectWiseData' => $projectWiseData 
        ]);
    }

    /**
     * @Route("/approval/update-status/{id}", name="lhg_payroll_approval_status_update", methods={"POST"})
     */
    public function updateStatus(Request $request, int $id)
    {
        // Retrieve the LhgPayrollApproval entity based on the provided ID
        $approval = $this->getDoctrine()->getRepository(LhgPayrollApproval::class)->find($id);

        $users = $this->teamLeadAndFinanceService->getTeamUsers();

        $teamMemberuserId = [];
        foreach ($users as $user) {
            array_push($teamMemberuserId, $user->getId());
        }

        if (!in_array($approval->getUser()->getId(), $teamMemberuserId)) {
            throw $this->createNotFoundException('You are not authorized to view this');
        }

        if (!$approval) {
            throw $this->createNotFoundException('Payroll approval not found');
        }

        $entityManager = $this->getDoctrine()->getManager(); 
            
        // Retrieve the data from the AJAX request
        $requestData = json_decode($request->getContent(), true); 

        $approvalHistory = new LhgPayrollApprovalHistory();

        // Set user and status for approval history
        $approvalHistory
            ->setUser($this->getUser())
            ->setApproval($approval)
            ->setMessage($requestData['message'])
            ->setDate( new DateTime())
            ->setStatus($requestData['status']);

        // If the status is approved with details (status 4)
        if ($requestData['status'] === StatusEnum::APPROVED_BY_FINANCE) {
            $approval
                ->setCommission($requestData['totalCommission'])
                ->setAdjustment($requestData['totalAdjustment'])
                ->setDeduction($requestData['totalDeduction'])
                ->setNetPayable($requestData['newTotal'])
                ->setPaymentMethod($requestData['paymentMethod']);
        }
        

        // Persist and flush the approval history
        $entityManager->persist($approvalHistory);
        $entityManager->flush();

        // Update the approval status
        $approval->setStatus($requestData['status']);

        // Persist and flush the approval entity
        $entityManager->persist($approval);
        $entityManager->flush();

        // Prepare the response data
        $responseData = [
            'message' => 'Payroll approval updated successfully',
            'approval' => $approval
        ]; 

        // Create a JSON response object
        $response = new JsonResponse($responseData);

        return $response; 

        // Return a regular Symfony response if not an AJAX request
        // return $this->redirectToRoute('lhg_payroll_approval_view', ['id' => $id]);
    }


        
}

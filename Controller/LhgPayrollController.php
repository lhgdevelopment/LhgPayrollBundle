<?php

namespace KimaiPlugin\LhgPayrollBundle\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use DateTime; 
use Doctrine\ORM\EntityManagerInterface;
use DoctrineExtensions\Query\Mysql\Format;
use KimaiPlugin\LhgPayrollBundle\Entity\LhgPayrollApproval;
use KimaiPlugin\LhgPayrollBundle\Entity\LhgPayrollApprovalHistory;
use KimaiPlugin\LhgPayrollBundle\Service\PayrollCalculatorService;
use KimaiPlugin\LhgPayrollBundle\Service\StatusEnum;
use KimaiPlugin\LhgPayrollBundle\Service\TeamLeadAndFinanceService;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response; 
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken; 
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security; 
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @Route(path="/admin/payroll")
 */
class LhgPayrollController extends AbstractController
{ 
    private $session;
    private $security; 
    private $payrollCalculatorService;
    private $teamLeadAndFinanceService;
    private $logger;
    private $userRepository;
    private $entityManager;

    public function __construct(SessionInterface $session, 
    Security $security, 
    PayrollCalculatorService $payrollCalculatorService, 
    UserRepository $userRepository, 
    LoggerInterface $logger, 
    TeamLeadAndFinanceService $teamLeadAndFinanceService, 
    EntityManagerInterface $entityManager)
    { 
        $this->session = $session;
        $this->security = $security; 
        $this->payrollCalculatorService = $payrollCalculatorService;
        $this->teamLeadAndFinanceService = $teamLeadAndFinanceService;
        $this->logger = $logger;
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
    }

    /**
     * @Route(path="", name="payroll", methods={"GET", "POST"})

     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction(Request $request, SessionInterface $session, AuthorizationCheckerInterface $auth): Response
    {
        // echo $this->session->get('INTERACTIVE_LOGIN_AS');
        // echo json_encode($this->session->get('INTERACTIVE_LOGIN_ORIGINAL')); 
        // echo $session->get('INTERACTIVE_LOGIN'); 
        // exit;

        $isLoggedInAs = $session->get('INTERACTIVE_LOGIN'); 

        if ($auth->isGranted('ROLE_SUPER_ADMIN') || $isLoggedInAs == 1) {
            $userRepository = $this->getDoctrine()->getRepository(User::class);
            $users = $userRepository->findAll(); 

            if ($request->isMethod('POST')) {
                $selectedUserId = $request->request->get('user');
                $selectedUser = $userRepository->find($selectedUserId);
                
                if ($selectedUser) {
                    $orignalUser = $this->security->getUser();
                    $token = new UsernamePasswordToken($selectedUser, null, 'main', $selectedUser->getRoles());
                    $this->get('security.token_storage')->setToken($token); 

                    $user = $this->security->getUser();
                    $session->set('INTERACTIVE_LOGIN', 1);
                    $session->set('INTERACTIVE_LOGIN_AS', $user->getUsername());

                    if($session->get('INTERACTIVE_LOGIN_ORIGINAL') == null){  
                        $session->set('INTERACTIVE_LOGIN_ORIGINAL', [
                            'name' => $orignalUser->getUsername(), 
                            'id'   => $orignalUser->getId()
                        ]);
                    }

                    // return $this->redirectToRoute('homepage');
                    return $this->redirectToRoute('payroll');
                }
            } 

            return $this->render('@LhgPayroll/index.html.twig', [
                'users' => $users,
                'isLoggedInAs' => $isLoggedInAs, 
                'loggedInAs' => $this->session->get('INTERACTIVE_LOGIN_AS'),
                'originalUser' => $this->session->get('INTERACTIVE_LOGIN_ORIGINAL')

            ]);
        }
        else{
            return new Response('Access denied', Response::HTTP_FORBIDDEN);
        }
    }

    /**
     * @Route(path="/exit", name="exit-payroll", methods={"GET"})

     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function exitUserAction(Request $request): Response
    { 
        $userRepository = $this->getDoctrine()->getRepository(User::class);

        // echo $this->session->get('INTERACTIVE_LOGIN_ORIGINAL');
        // exit;

        if ($this->session->get('INTERACTIVE_LOGIN_ORIGINAL') != null) { 
            $selectedUser = $userRepository->find($this->session->get('INTERACTIVE_LOGIN_ORIGINAL')['id']);

            if ($selectedUser) {
                $token = new UsernamePasswordToken($selectedUser, null, 'main', $selectedUser->getRoles());
                $this->get('security.token_storage')->setToken($token);
                $this->session->remove('INTERACTIVE_LOGIN');
                $this->session->remove('INTERACTIVE_LOGIN_AS'); 
                $this->session->remove('INTERACTIVE_LOGIN_ORIGINAL'); 

                return $this->redirectToRoute('payroll');
            }
        }

        return $this->redirectToRoute('payroll');
    }

    /**
     * @Route(path="/biweekly", name="biweekly-payroll", methods={"GET"})

     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */

    public function biweeklyPayrollAction(Request $request, AuthorizationCheckerInterface $auth)
    {
        
        $users = $this->teamLeadAndFinanceService->getTeamUsers();

        // Get the date and user input from the request
        $selectedDate = $request->query->get('date', new DateTime());
        if($request->query->get('date')){
            $selectedDate = new DateTime($request->query->get('date'));
        }
        else{
            $selectedDate = new DateTime();
        }

        $dates = $this->payrollCalculatorService->calculateBiweeklyPeriod($selectedDate); 
        $biweeklyStart = $dates['start'];
        $biweeklyEnd = $dates['end']; 

        $notSubmittedYet = [];

        if($this->teamLeadAndFinanceService->isTeamLead() && !$auth->isGranted('ROLE_SUPER_ADMIN')){
            $teamMemberuserId = [];
            foreach($users as $user){
                array_push($teamMemberuserId, $user->getId());
            }

            $submittedData = $this->entityManager->getRepository(LhgPayrollApproval::class)->FindBy([
                'user' => $teamMemberuserId, 
                'startDate' => $dates['start'] , 
                'status' => StatusEnum::PENDING
            ]); 

            $approvedByTeamLead = $this->entityManager->getRepository(LhgPayrollApproval::class)->FindBy([
                'user' => $teamMemberuserId, 
                'startDate' => $dates['start'] ,
                'status' => StatusEnum::APPROVED_BY_TEAM_LEAD
            ]);
            $approvedData = [];
            $notSubmittedUsers = [];
        }
        else if($auth->isGranted('ROLE_SUPER_ADMIN')){ 
            // Get not submitted data set 
            $submittedApprovalData = $this->entityManager->getRepository(LhgPayrollApproval::class)->FindBy([ 
                'startDate' => $dates['start'] 
            ]);

            $submitedUsers = [];
            foreach($submittedApprovalData as $submitteddata){
                array_push($submitedUsers, $submitteddata->getUser()->getId());
            }
            $queryBuilder = $this->entityManager->createQueryBuilder();

            if(sizeof($submitedUsers) > 0){
                $notSubmittedUsers = $queryBuilder
                ->select('u')
                ->from(User::class, 'u')
                ->where($queryBuilder->expr()->notIn('u.id', $submitedUsers))
                ->andWhere('u.enabled = :enabled')
                ->setParameter('enabled', 1)
                ->getQuery()
                ->getResult();
            }
            else{
                $notSubmittedUsers = $queryBuilder
                ->select('u')
                ->from(User::class, 'u') 
                ->andWhere('u.enabled = :enabled')
                ->setParameter('enabled', 1)
                ->getQuery()
                ->getResult();
            }

            // dd($notSubmittedUsers);
            // dd('Yes Admin');
            // $toApproveData = $this->entityManager->getRepository(LhgPayrollApproval::class)->FindBy([ 
            //     'startDate' => $dates['start'] ,
            //     'status' => StatusEnum::APPROVED_BY_TEAM_LEAD
            // ]);
            // // Process Team Member Data
            // if($this->teamLeadAndFinanceService->isTeamLead()){
            //     // Get admin team members 
            //     $teamMembers = $this->teamLeadAndFinanceService->getTeamMemberForTeamLead();
            //     if(sizeof($teamMembers) > 0){
            //         $pendingData = $this->entityManager->getRepository(LhgPayrollApproval::class)->FindBy([
            //             'user' => $teamMembers, 
            //             'startDate' => $dates['start'] , 
            //             'status' => StatusEnum::PENDING
            //         ]);
            //         // dd($pendingData);

            //         $toApproveData = array_merge($pendingData, $toApproveData);
            //     }
            // }

            // Submitted 
            $submittedData = $this->entityManager->getRepository(LhgPayrollApproval::class)->FindBy([ 
                'startDate' => $dates['start'] ,
                'status' => StatusEnum::PENDING
            ]);

            $approvedByTeamLead = $this->entityManager->getRepository(LhgPayrollApproval::class)->FindBy([ 
                'startDate' => $dates['start'] ,
                'status' => StatusEnum::APPROVED_BY_TEAM_LEAD
            ]); 

            $approvedData = $this->entityManager->getRepository(LhgPayrollApproval::class)->findBy([ 
                'startDate' =>$dates['start'], 
                'status' => StatusEnum::APPROVED_BY_FINANCE
            ]);
        }
        else{
            // $toApproveData = [];
            $submittedData = [];
            $approvedData = [];
            $approvedByTeamLead = [];
            $notSubmittedUsers = [];
        }

        $selectedUser = $this->getUser();
        
        if($request->query->get('user')){
            if($auth->isGranted('ROLE_SUPER_ADMIN')){
                $selectedUser = $this->userRepository->getUserById($request->query->get('user')); 
            }

            else if($this->teamLeadAndFinanceService->isTeamLead()){ 
                if($this->teamLeadAndFinanceService->isInTeam($request->query->get('user'))){ 
                    $selectedUser = $this->userRepository->getUserById($request->query->get('user'));
                }
                else{
                    $selectedUser = $this->getUser(); 
                } 
            }
            else{
                $selectedUser = $this->getUser(); 
            }
        }  

        // Calculate biweekly payroll data
        [$timesheets, $errors] = $this->payrollCalculatorService->getTimesheets($selectedUser, $biweeklyStart, $biweeklyEnd);

        // Prepare Projectwise data 

        $projectWiseData = [];

        // Loop through each work log
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

        $submittedByUser = false;

        $existingApproval = $this->entityManager->getRepository(LhgPayrollApproval::class)->findOneBy([
            'user' => $selectedUser->getId(),
            'startDate' => $dates['start'], 
        ]);
        // dd($existingApproval);
        $approvalHistory = null;
        if($existingApproval){
            $submittedByUser = true;
            $approvalHistory = $this->getDoctrine()->getRepository(LhgPayrollApprovalHistory::class)->findBy([
                'approval' => $existingApproval 
            ]); 
        }


        // Render the template with payroll data
        $reflection = new ReflectionClass('KimaiPlugin\LhgPayrollBundle\Service\StatusEnum');
        $constants = $reflection->getConstants();

        $enumValuePairs = [];
        foreach ($constants as $constantName => $constantValue) {
            $enumValuePairs[$constantValue] = $constantName;
        }
        // dd($enumValuePairs[1]);
        return $this->render('@LhgPayroll/payroll/biweekly.html.twig', [
            'submittedData' => $submittedData,
            'approvedByTeamLead' => $approvedByTeamLead,
            'approvedData' => $approvedData,
            'auth' => $auth,
            'users' => $users,
            'payrollData' => $payrollData,
            'timesheets' => $timesheets, 
            'projectWiseData' => $projectWiseData,
            'selectedDate' => $selectedDate->format('Y-m-d'),
            'selectedUserName' => $selectedUser->getAlias() ?? $selectedUser->getUsername(),
            'selectedUserId' => $selectedUser->getId(),
            'payrollDates' => [
                'start' => $dates['start']->format('M-d-Y'),
                'end' => $dates['end']->format('M-d-Y')
            ],
            'submittedByUser' => $submittedByUser,
            'approvalHistory' => $approvalHistory,
            'approval' => $existingApproval,
            'statusArray' => $enumValuePairs,
            'notSubmittedUsers' => $notSubmittedUsers,
        ]);
    }
}

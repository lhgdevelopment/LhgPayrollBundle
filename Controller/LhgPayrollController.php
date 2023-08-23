<?php

namespace KimaiPlugin\LhgPayrollBundle\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use DateTime; 
use Doctrine\ORM\EntityManagerInterface;
use DoctrineExtensions\Query\Mysql\Format;
use KimaiPlugin\LhgPayrollBundle\Service\PayrollCalculatorService;
use Psr\Log\LoggerInterface;
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
    private $logger;
    private $userRepository;

    public function __construct(SessionInterface $session, 
    Security $security, 
    PayrollCalculatorService $payrollCalculatorService, 
    UserRepository $userRepository, 
    LoggerInterface $logger)
    { 
        $this->session = $session;
        $this->security = $security; 
        $this->payrollCalculatorService = $payrollCalculatorService;
        $this->logger = $logger;
        $this->userRepository = $userRepository;
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

        // if(!$auth->isGranted('ROLE_SUPER_ADMIN')){
        //     return new Response('Access denied', Response::HTTP_FORBIDDEN);
        // }
        $users = $this->userRepository->findAll();

        // Get the date and user input from the request
        $selectedDate = $request->query->get('date', new DateTime());
        if($request->query->get('date')){
            $selectedDate = new DateTime($request->query->get('date'));
        }
        else{
            $selectedDate = new DateTime();
        }
        $selectedUser = $this->getUser();
        if($request->query->get('user')){
            $selectedUser = $this->userRepository->getUserById($request->query->get('user'));
        }

        if(!$auth->isGranted('ROLE_SUPER_ADMIN')){
            $selectedUser = $this->getUser();
        }
        

        $dates = $this->payrollCalculatorService->calculateBiweeklyPeriod($selectedDate);
        // $selectedDate = $dates['start'];
        $biweeklyStart = $dates['start'];
        $biweeklyEnd = $dates['end'];

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
        
        // echo '<pre>';
        // print_r(json_encode($projectWiseData)); 
        // echo '</pre>';
        // exit();
        
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

        // Render the template with payroll data
        return $this->render('@LhgPayroll/payroll/biweekly.html.twig', [
            'auth' => $auth,
            'users' => $users,
            'payrollData' => $payrollData,
            'timesheets' => $timesheets, 
            'projectWiseData' => $projectWiseData,
            'selectedDate' => $selectedDate->format('Y-m-d'),
            'selectedUserName' => $selectedUser->getAlias() ?? $selectedUser->getUsername(),
            'selectedUserId' => $selectedUser->getId(),
            'payrollDates' => [
                'start' => $dates['start']->format('M-d-y'),
                'end' => $dates['end']->format('M-d-y')
            ]
        ]);
    }
}

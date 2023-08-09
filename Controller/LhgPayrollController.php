<?php

namespace KimaiPlugin\LhgPayrollBundle\Controller;

use App\Entity\User;
use DateInterval;
use DateTime;
use DateTimeImmutable;
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

    public function __construct(SessionInterface $session, 
    Security $security, 
    PayrollCalculatorService $payrollCalculatorService)
    { 
        $this->session = $session;
        $this->security = $security; 
        $this->payrollCalculatorService = $payrollCalculatorService;
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

    public function biweeklyPayrollAction(Request $request)
    {

        // 07.17.2023 - 07.30.2023 => Latest 
        // 07.31.2023 - 08.13.2023 => Next 
        // Get the current date 
        
        $dates = $this->payrollCalculatorService->calculateBiweeklyPeriod(new DateTime());

        // Calculate the biweekly start and end dates based on the current date
        $biweeklyStart = $dates['start'];        
        $biweeklyEnd = $dates['end']; 
        // print_r($dates);
        // exit;
        // Fetch the logged-in user
        $user = $this->getUser();

        // Calculate biweekly payroll data
        // $payrollData = $this->payrollCalculatorService->calculateBiweeklyPayroll($user, $biweeklyStart, $biweeklyEnd);
        $payrollData = $this->payrollCalculatorService->getTimesheets($user, $biweeklyStart, $biweeklyEnd); 

        // Render the template with payroll data
        return $this->render('@LhgPayroll/payroll/biweekly.html.twig', [
            'payrollData' => $payrollData,
        ]);
    }
}

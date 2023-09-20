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
 * @Route(path="/admin/payroll-export")
 */
class LhgExportPayrollController extends AbstractController
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
     * @Route(path="", name="payrollExport", methods={"GET", "POST"})

     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction(Request $request, SessionInterface $session, AuthorizationCheckerInterface $auth): Response
    {
        
        return new Response('Export Payroll PDF'); 
    }

    
}

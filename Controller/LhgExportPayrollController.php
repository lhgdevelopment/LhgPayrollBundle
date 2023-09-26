<?php

namespace KimaiPlugin\LhgPayrollBundle\Controller;

use App\Controller\TimesheetAbstractController;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Export\ServiceExport;
use App\Repository\UserRepository;
use DateTime; 
use Doctrine\ORM\EntityManagerInterface;
use DoctrineExtensions\Query\Mysql\Format;
use KimaiPlugin\LhgPayrollBundle\Entity\LhgPayrollApproval;
use KimaiPlugin\LhgPayrollBundle\Entity\LhgPayrollApprovalHistory;
use KimaiPlugin\LhgPayrollBundle\Service\PayrollCalculatorService;
use KimaiPlugin\LhgPayrollBundle\Service\StatusEnum;
use KimaiPlugin\LhgPayrollBundle\Service\TeamLeadAndFinanceService;
use Mpdf\Mpdf;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Test\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response; 
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken; 
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security; 
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

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
    // public function indexAction(Request $request, SessionInterface $session, AuthorizationCheckerInterface $auth): Response
    // {
        
    //     return new Response('Export Payroll PDF'); 
    // }

    public function indexAction(Request $request, HttpClientInterface $httpClient, UrlGeneratorInterface $urlGenerator): Response
    {
        return $this->generatePdf();
    }

    public function generatePdf(): Response
    {
        // Create an instance of mPDF
        $mpdf = new Mpdf();

        // Your HTML content to be converted to PDF
        $html = $this->renderView('@LhgPayroll/payroll/export.html.twig', [
            'data' => 'Hello, mPDF!'
        ]);

        // Load HTML content into mPDF
        $mpdf->WriteHTML($html);

        // Output the PDF as a response
        return new Response($mpdf->Output(), 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * @Route(path="/pdf", name="payrollExportPDF", methods={"GET", "POST"})

     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function pdf(): Response
    {
        // Create a new mPDF instance
        $mpdf = new Mpdf();

        // Your HTML content goes here. You can generate it dynamically or load from a template.
        $htmlContent = '<h1>Hello, PDF!</h1>';

        // Generate PDF from HTML content
        $mpdf->WriteHTML($htmlContent);

        // Output the PDF to the browser or save it to a file
        $mpdf->Output();

        // Return a Symfony Response
        return new Response();
    }

     

    
}

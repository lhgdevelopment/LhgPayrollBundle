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
class LhgExportPayrollController extends TimesheetAbstractController
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

        // Define the URL of your internal route
        // $url = $this->generateUrl('timesheet_export');
        $url = $this->generateUrl('timesheet_export', [], UrlGeneratorInterface::ABSOLUTE_URL); 

        // Create an array of parameters to set in the request body
        $requestParameters = [
            'export' => [
                'daterange' => $biweeklyStart->format('Y-m-d') . ' - ' . $biweeklyEnd->format('Y-m-d'),
                'export' => 'pdf',
                'state' => 1,
                'exported' => 1,
                'orderBy' => 'begin',
                'order' => 'ASC',
            ],
        ];
        dd($requestParameters);

        // Make the API call with the specified parameters
        $response = $httpClient->request('POST', $url, [
            'json' => $requestParameters,
        ]);

        // Check if the response status code indicates success (e.g., 200 OK)
        if ($response->getStatusCode() === 200) {
            // Retrieve the PDF content from the response
            $pdfContent = $response->getContent();

            // Create a Symfony Response containing the PDF content
            $response = new Response($pdfContent);

            // Set the response headers to indicate that it's a PDF
            $response->headers->set('Content-Type', 'application/pdf');
            $response->headers->set('Content-Disposition', 'inline; filename="your-pdf-file.pdf"');

            return $response;
        } else {
            // Handle the case when the API call does not return a PDF
            return new Response('Failed to retrieve PDF', $response->getStatusCode());
        }
    }

    protected function getCreateForm(Timesheet $entry): FormInterface
    {
        return $this->generateCreateForm($entry, TimesheetEditForm::class, $this->generateUrl('timesheet_create'));
    }

    protected function getDuplicateForm(Timesheet $entry, Timesheet $original): FormInterface
    {
        return $this->generateCreateForm($entry, TimesheetEditForm::class, $this->generateUrl('timesheet_duplicate', ['id' => $original->getId()]));
    }

    
}

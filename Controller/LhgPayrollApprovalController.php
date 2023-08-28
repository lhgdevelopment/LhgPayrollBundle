<?php

namespace KimaiPlugin\LhgPayrollBundle\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use KimaiPlugin\LhgPayrollBundle\Entity\LhgPayrollApproval;
use KimaiPlugin\LhgPayrollBundle\Form\LhgPayrollApprovalType;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route(path="/admin/payroll-approval")
 */
class LhgPayrollApprovalController extends AbstractController
{

    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
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

        // Set properties using request data
        $user = $this->entityManager->getRepository(User::class)->find($requestData['userId']);
        $approval
            ->setUser($user)
            ->setSubmittedBy($this->getUser())
            ->setStartDate(new \DateTime($requestData['startDate']))
            ->setEndDate(new \DateTime($requestData['endDate']))
            ->setStatus(1) // Set your desired status
            ->setExpectedDuration(0)
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

        if (!$approval) {
            // Handle the case when the approval is not found
            throw $this->createNotFoundException('Payroll approval not found');
        }

        return $this->render('@LhgPayroll/approval/view.html.twig', ['approval' => $approval]);
    }


        
}

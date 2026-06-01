<?php

namespace KimaiPlugin\LhgPayrollBundle\Controller\API;

use App\API\BaseApiController;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\LhgPayrollBundle\Entity\LhgPayrollApproval;
use KimaiPlugin\LhgPayrollBundle\Service\PayrollApprovalService;
use KimaiPlugin\LhgPayrollBundle\Service\PayrollBiweeklyService;
use KimaiPlugin\LhgPayrollBundle\Service\StatusEnum;
use Nelmio\ApiDocBundle\Annotation\Security as ApiSecurity;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(path="/api/payroll/approvals")
 * @SWG\Tag(name="LHG Payroll Approvals")
 *
 * @Security("is_granted('IS_AUTHENTICATED_REMEMBERED')")
 * @ApiSecurity(name="apiUser")
 * @ApiSecurity(name="apiToken")
 */
class PayrollApprovalApiController extends BaseApiController
{
    private $entityManager;
    private $payrollApprovalService;
    private $payrollBiweeklyService;

    public function __construct(
        EntityManagerInterface $entityManager,
        PayrollApprovalService $payrollApprovalService,
        PayrollBiweeklyService $payrollBiweeklyService
    ) {
        $this->entityManager = $entityManager;
        $this->payrollApprovalService = $payrollApprovalService;
        $this->payrollBiweeklyService = $payrollBiweeklyService;
    }

    /**
     * @Route(path="", name="api_payroll_approvals_list", methods={"GET"})
     * @Security("is_granted('api_payroll_view_own')")
     * @SWG\Parameter(name="status", in="query", type="integer")
     * @SWG\Parameter(name="start_date", in="query", type="string", description="Period start Y-m-d")
     * @SWG\Response(response=200, description="List of approvals visible to the current user")
     */
    public function listAction(Request $request): JsonResponse
    {
        $actor = $this->getUser();
        if (!$actor instanceof User) {
            return $this->errorResponse('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        $criteria = [];
        if ($request->query->has('status')) {
            $criteria['status'] = (int) $request->query->get('status');
        }
        if ($request->query->get('start_date')) {
            $criteria['startDate'] = new \DateTime($request->query->get('start_date'));
        }

        $approvals = $this->entityManager->getRepository(LhgPayrollApproval::class)->findBy(
            $criteria,
            ['creationDate' => 'DESC']
        );

        $visible = [];
        foreach ($approvals as $approval) {
            try {
                $this->payrollApprovalService->assertCanAccessApproval($approval, $actor);
                $visible[] = $this->payrollApprovalService->normalizeApproval($approval);
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                continue;
            }
        }

        return $this->jsonResponse(['approvals' => $visible]);
    }

    /**
     * @Route(path="/{id}", name="api_payroll_approval_get", methods={"GET"}, requirements={"id": "\d+"})
     * @Security("is_granted('api_payroll_view_own')")
     * @SWG\Response(response=200, description="Approval detail with timesheets")
     */
    public function getAction(int $id): JsonResponse
    {
        $actor = $this->getUser();
        if (!$actor instanceof User) {
            return $this->errorResponse('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        try {
            $data = $this->payrollBiweeklyService->getApprovalDetail($id, $actor);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->errorResponse($e->getMessage(), $e->getStatusCode());
        }

        return $this->jsonResponse($data);
    }

    /**
     * @Route(path="", name="api_payroll_approval_submit", methods={"POST"})
     * @Security("is_granted('api_payroll_view_own')")
     * @SWG\Response(response=201, description="Approval submitted")
     */
    public function submitAction(Request $request): JsonResponse
    {
        $actor = $this->getUser();
        if (!$actor instanceof User) {
            return $this->errorResponse('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->errorResponse('Invalid JSON body', Response::HTTP_BAD_REQUEST);
        }

        foreach (['userId', 'startDate', 'endDate'] as $field) {
            if (empty($payload[$field])) {
                return $this->errorResponse(sprintf('Missing required field: %s', $field), Response::HTTP_BAD_REQUEST);
            }
        }

        try {
            $result = $this->payrollApprovalService->submit(
                (int) $payload['userId'],
                $payload['startDate'],
                $payload['endDate'],
                $actor
            );
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->errorResponse($e->getMessage(), $e->getStatusCode());
        }

        return $this->jsonResponse($result, Response::HTTP_CREATED);
    }

    /**
     * @Route(path="/{id}/status", name="api_payroll_approval_status", methods={"POST"}, requirements={"id": "\d+"})
     * @Security("is_granted('api_payroll_view_own')")
     * @SWG\Response(response=200, description="Approval status updated (team lead / finance enforced in service)")
     */
    public function updateStatusAction(Request $request, int $id): JsonResponse
    {
        $actor = $this->getUser();
        if (!$actor instanceof User) {
            return $this->errorResponse('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload) || !isset($payload['status'])) {
            return $this->errorResponse('Missing required field: status', Response::HTTP_BAD_REQUEST);
        }

        $financeData = [];
        if ((int) $payload['status'] === StatusEnum::APPROVED_BY_FINANCE) {
            $financeData = [
                'commission' => $payload['totalCommission'] ?? $payload['commission'] ?? 0,
                'adjustment' => $payload['totalAdjustment'] ?? $payload['adjustment'] ?? 0,
                'deduction' => $payload['totalDeduction'] ?? $payload['deduction'] ?? 0,
                'netPayable' => $payload['newTotal'] ?? $payload['netPayable'] ?? 0,
                'paymentMethod' => $payload['paymentMethod'] ?? null,
            ];
        }

        try {
            $result = $this->payrollApprovalService->updateStatus(
                $id,
                (int) $payload['status'],
                $payload['message'] ?? null,
                $actor,
                $financeData
            );
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->errorResponse($e->getMessage(), $e->getStatusCode());
        }

        return $this->jsonResponse($result);
    }

    /**
     * @Route(path="/{id}/resubmit", name="api_payroll_approval_resubmit", methods={"POST"}, requirements={"id": "\d+"})
     * @Security("is_granted('api_payroll_view_own')")
     * @SWG\Response(response=200, description="Approval re-submitted")
     */
    public function resubmitAction(int $id): JsonResponse
    {
        $actor = $this->getUser();
        if (!$actor instanceof User) {
            return $this->errorResponse('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        try {
            $result = $this->payrollApprovalService->resubmit($id, $actor);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->errorResponse($e->getMessage(), $e->getStatusCode());
        }

        return $this->jsonResponse($result);
    }

    private function jsonResponse(array $data, int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse([
            'code' => $status,
            'data' => $data,
        ], $status);
    }

    private function errorResponse(string $message, int $status): JsonResponse
    {
        return new JsonResponse([
            'code' => $status,
            'message' => $message,
        ], $status);
    }
}

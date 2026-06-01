<?php

namespace KimaiPlugin\LhgPayrollBundle\Controller\API;

use App\API\BaseApiController;
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
     * @Route(path="/api/payroll/approvals", name="api_payroll_approvals_list", methods={"GET"})
     *
     * @SWG\Get(
     *     tags={"LHG Payroll API"},
     *     summary="Lists payroll approvals visible to the current user",
     *     @SWG\Parameter(name="status", in="query", type="integer", required=false),
     *     @SWG\Parameter(name="start_date", in="query", type="string", required=false, description="Period start Y-m-d")
     * )
     * @SWG\Response(response=200, description="List of approvals")
     *
     * @Security("is_granted('IS_AUTHENTICATED_REMEMBERED') and is_granted('api_payroll_view_own')")
     * @ApiSecurity(name="apiUser")
     * @ApiSecurity(name="apiToken")
     */
    public function listAction(Request $request): JsonResponse
    {
        $actor = $this->getUser();

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
     * @Route(path="/api/payroll/approvals/{id}", name="api_payroll_approval_get", methods={"GET"}, requirements={"id": "\d+"})
     *
     * @SWG\Get(
     *     tags={"LHG Payroll API"},
     *     summary="Returns approval detail with timesheets and history",
     *     @SWG\Parameter(name="id", in="path", type="integer", required=true, description="Approval ID")
     * )
     * @SWG\Response(response=200, description="Approval detail")
     * @SWG\Response(response=403, description="Access denied")
     * @SWG\Response(response=404, description="Not found")
     *
     * @Security("is_granted('IS_AUTHENTICATED_REMEMBERED') and is_granted('api_payroll_view_own')")
     * @ApiSecurity(name="apiUser")
     * @ApiSecurity(name="apiToken")
     */
    public function getAction(int $id): JsonResponse
    {
        try {
            $data = $this->payrollBiweeklyService->getApprovalDetail($id, $this->getUser());
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->errorResponse($e->getMessage(), $e->getStatusCode());
        }

        return $this->jsonResponse($data);
    }

    /**
     * @Route(path="/api/payroll/approvals", name="api_payroll_approval_submit", methods={"POST"})
     *
     * @SWG\Post(
     *     tags={"LHG Payroll API"},
     *     summary="Submits a biweekly payroll period for approval",
     *     @SWG\Parameter(
     *         name="body",
     *         in="body",
     *         required=true,
     *         @SWG\Schema(
     *             type="object",
     *             required={"userId", "startDate", "endDate"},
     *             @SWG\Property(property="userId", type="integer", example=5),
     *             @SWG\Property(property="startDate", type="string", format="date", example="2026-05-18"),
     *             @SWG\Property(property="endDate", type="string", format="date", example="2026-05-31")
     *         )
     *     )
     * )
     * @SWG\Response(response=201, description="Approval submitted")
     * @SWG\Response(response=409, description="Duplicate approval")
     *
     * @Security("is_granted('IS_AUTHENTICATED_REMEMBERED') and is_granted('api_payroll_view_own')")
     * @ApiSecurity(name="apiUser")
     * @ApiSecurity(name="apiToken")
     */
    public function submitAction(Request $request): JsonResponse
    {
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
                $this->getUser()
            );
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->errorResponse($e->getMessage(), $e->getStatusCode());
        }

        return $this->jsonResponse($result, Response::HTTP_CREATED);
    }

    /**
     * @Route(path="/api/payroll/approvals/{id}/status", name="api_payroll_approval_status", methods={"POST"}, requirements={"id": "\d+"})
     *
     * @SWG\Post(
     *     tags={"LHG Payroll API"},
     *     summary="Updates approval status (team lead or finance approve/reject)",
     *     @SWG\Parameter(name="id", in="path", type="integer", required=true, description="Approval ID"),
     *     @SWG\Parameter(
     *         name="body",
     *         in="body",
     *         required=true,
     *         @SWG\Schema(
     *             type="object",
     *             required={"status"},
     *             @SWG\Property(property="status", type="integer", example=2, description="StatusEnum value"),
     *             @SWG\Property(property="message", type="string", example="Approved"),
     *             @SWG\Property(property="commission", type="number", example=0),
     *             @SWG\Property(property="adjustment", type="number", example=50),
     *             @SWG\Property(property="deduction", type="number", example=0),
     *             @SWG\Property(property="netPayable", type="number", example=1250.5),
     *             @SWG\Property(property="paymentMethod", type="string", example="Wise")
     *         )
     *     )
     * )
     * @SWG\Response(response=200, description="Status updated")
     * @SWG\Response(response=403, description="Access denied")
     *
     * @Security("is_granted('IS_AUTHENTICATED_REMEMBERED') and is_granted('api_payroll_view_own')")
     * @ApiSecurity(name="apiUser")
     * @ApiSecurity(name="apiToken")
     */
    public function updateStatusAction(Request $request, int $id): JsonResponse
    {
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
                $this->getUser(),
                $financeData
            );
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->errorResponse($e->getMessage(), $e->getStatusCode());
        }

        return $this->jsonResponse($result);
    }

    /**
     * @Route(path="/api/payroll/approvals/{id}/resubmit", name="api_payroll_approval_resubmit", methods={"POST"}, requirements={"id": "\d+"})
     *
     * @SWG\Post(
     *     tags={"LHG Payroll API"},
     *     summary="Re-submits a rejected payroll approval",
     *     @SWG\Parameter(name="id", in="path", type="integer", required=true, description="Approval ID")
     * )
     * @SWG\Response(response=200, description="Approval re-submitted")
     *
     * @Security("is_granted('IS_AUTHENTICATED_REMEMBERED') and is_granted('api_payroll_view_own')")
     * @ApiSecurity(name="apiUser")
     * @ApiSecurity(name="apiToken")
     */
    public function resubmitAction(int $id): JsonResponse
    {
        try {
            $result = $this->payrollApprovalService->resubmit($id, $this->getUser());
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

<?php

namespace KimaiPlugin\LhgPayrollBundle\Controller\API;

use App\API\BaseApiController;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\LhgPayrollBundle\Entity\LhgPayrollApproval;
use KimaiPlugin\LhgPayrollBundle\Service\PayrollApprovalService;
use KimaiPlugin\LhgPayrollBundle\Service\PayrollBiweeklyService;
use KimaiPlugin\LhgPayrollBundle\Service\StatusEnum;
use Nelmio\ApiDocBundle\Attribute\Security as ApiSecurity;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/payroll/approvals')]
#[OA\Tag(name: 'LHG Payroll Approvals')]
#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
#[ApiSecurity(name: 'apiUser')]
#[ApiSecurity(name: 'apiToken')]
class PayrollApprovalApiController extends BaseApiController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PayrollApprovalService $payrollApprovalService,
        private readonly PayrollBiweeklyService $payrollBiweeklyService
    ) {
    }

    #[Route(path: '', name: 'api_payroll_approvals_list', methods: ['GET'])]
    #[IsGranted('api_payroll_view_own')]
    #[OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'start_date', in: 'query', schema: new OA\Schema(type: 'string'), description: 'Period start Y-m-d')]
    #[OA\Response(response: 200, description: 'List of approvals visible to the current user')]
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

    #[Route(path: '/{id}', name: 'api_payroll_approval_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted('api_payroll_view_own')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Approval detail with timesheets')]
    #[OA\Response(response: 403, description: 'Access denied')]
    #[OA\Response(response: 404, description: 'Not found')]
    public function getAction(int $id): JsonResponse
    {
        try {
            $data = $this->payrollBiweeklyService->getApprovalDetail($id, $this->getUser());
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->errorResponse($e->getMessage(), $e->getStatusCode());
        }

        return $this->jsonResponse($data);
    }

    #[Route(path: '', name: 'api_payroll_approval_submit', methods: ['POST'])]
    #[IsGranted('api_payroll_view_own')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['userId', 'startDate', 'endDate'],
            properties: [
                new OA\Property(property: 'userId', type: 'integer'),
                new OA\Property(property: 'startDate', type: 'string', format: 'date'),
                new OA\Property(property: 'endDate', type: 'string', format: 'date'),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Approval submitted')]
    #[OA\Response(response: 409, description: 'Duplicate approval')]
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

    #[Route(path: '/{id}/status', name: 'api_payroll_approval_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('api_payroll_view_own')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['status'],
            properties: [
                new OA\Property(property: 'status', type: 'integer', description: 'StatusEnum value'),
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(property: 'commission', type: 'number'),
                new OA\Property(property: 'adjustment', type: 'number'),
                new OA\Property(property: 'deduction', type: 'number'),
                new OA\Property(property: 'netPayable', type: 'number'),
                new OA\Property(property: 'paymentMethod', type: 'string'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Approval status updated')]
    #[OA\Response(response: 403, description: 'Access denied')]
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

    #[Route(path: '/{id}/resubmit', name: 'api_payroll_approval_resubmit', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('api_payroll_view_own')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Approval re-submitted')]
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

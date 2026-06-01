<?php

namespace KimaiPlugin\LhgPayrollBundle\Controller\API;

use App\API\BaseApiController;
use App\Entity\User;
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
 * @Route(path="/api/payroll")
 * @SWG\Tag(name="LHG Payroll")
 *
 * @Security("is_granted('IS_AUTHENTICATED_REMEMBERED')")
 * @ApiSecurity(name="apiUser")
 * @ApiSecurity(name="apiToken")
 */
class PayrollApiController extends BaseApiController
{
    private $payrollBiweeklyService;

    public function __construct(PayrollBiweeklyService $payrollBiweeklyService)
    {
        $this->payrollBiweeklyService = $payrollBiweeklyService;
    }

    /**
     * @Route(path="/ping", name="api_payroll_ping", methods={"GET"})
     * @SWG\Response(response=200, description="Plugin API is reachable")
     */
    public function pingAction(): JsonResponse
    {
        return $this->jsonResponse([
            'status' => 'ok',
            'plugin' => 'LhgPayrollBundle',
        ]);
    }

    /**
     * @Route(path="/statuses", name="api_payroll_statuses", methods={"GET"})
     * @Security("is_granted('api_payroll_view_own')")
     * @SWG\Response(response=200, description="Payroll approval status codes")
     */
    public function statusesAction(): JsonResponse
    {
        $reflection = new \ReflectionClass(StatusEnum::class);
        $statuses = [];
        foreach ($reflection->getConstants() as $name => $value) {
            $statuses[] = [
                'value' => $value,
                'name' => $name,
            ];
        }

        return $this->jsonResponse(['statuses' => $statuses]);
    }

    /**
     * @Route(path="/period", name="api_payroll_period", methods={"GET"})
     * @Security("is_granted('api_payroll_view_own')")
     * @SWG\Parameter(name="date", in="query", type="string", description="Reference date (Y-m-d)")
     * @SWG\Response(response=200, description="Biweekly period boundaries")
     */
    public function periodAction(Request $request): JsonResponse
    {
        $period = $this->payrollBiweeklyService->resolvePeriod($request->query->get('date'));

        return $this->jsonResponse([
            'period' => [
                'selectedDate' => $period['selectedDate'],
                'start' => $period['start'],
                'end' => $period['end'],
            ],
        ]);
    }

    /**
     * @Route(path="/biweekly", name="api_payroll_biweekly", methods={"GET"})
     * @Security("is_granted('api_payroll_view_own')")
     * @SWG\Parameter(name="date", in="query", type="string", description="Reference date (Y-m-d)")
     * @SWG\Parameter(name="user_id", in="query", type="integer", description="Target user (team lead / admin only)")
     * @SWG\Response(response=200, description="Biweekly payroll detail for a user")
     */
    public function biweeklyAction(Request $request): JsonResponse
    {
        $userId = $request->query->get('user_id');
        $userId = $userId !== null && $userId !== '' ? (int) $userId : null;

        try {
            $data = $this->payrollBiweeklyService->getBiweeklyPayload(
                $request->query->get('date'),
                $userId
            );
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->errorResponse($e->getMessage(), $e->getStatusCode());
        }

        return $this->jsonResponse($data);
    }

    /**
     * @Route(path="/queues", name="api_payroll_queues", methods={"GET"})
     * @Security("is_granted('api_payroll_view_own')")
     * @SWG\Parameter(name="date", in="query", type="string", description="Reference date (Y-m-d)")
     * @SWG\Response(response=200, description="Approval queues for the biweekly period (team lead / admin)")
     */
    public function queuesAction(Request $request): JsonResponse
    {
        try {
            $data = $this->payrollBiweeklyService->getQueues($request->query->get('date'));
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->errorResponse($e->getMessage(), $e->getStatusCode());
        }

        return $this->jsonResponse($data);
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

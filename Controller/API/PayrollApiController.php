<?php

namespace KimaiPlugin\LhgPayrollBundle\Controller\API;

use App\API\BaseApiController;
use KimaiPlugin\LhgPayrollBundle\Service\PayrollBiweeklyService;
use KimaiPlugin\LhgPayrollBundle\Service\StatusEnum;
use Nelmio\ApiDocBundle\Annotation\Security as ApiSecurity;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PayrollApiController extends BaseApiController
{
    private $payrollBiweeklyService;

    public function __construct(PayrollBiweeklyService $payrollBiweeklyService)
    {
        $this->payrollBiweeklyService = $payrollBiweeklyService;
    }

    /**
     * @Route(path="/api/payroll/ping", name="api_payroll_ping", methods={"GET"})
     *
     * @SWG\Get(
     *     tags={"LHG Payroll API"},
     *     summary="Health check for the payroll plugin API"
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Plugin API is reachable",
     *     @SWG\Schema(
     *         type="object",
     *         @SWG\Property(property="code", type="integer", example=200),
     *         @SWG\Property(
     *             property="data",
     *             type="object",
     *             @SWG\Property(property="status", type="string", example="ok"),
     *             @SWG\Property(property="plugin", type="string", example="LhgPayrollBundle")
     *         )
     *     )
     * )
     *
     * @ApiSecurity(name="apiUser")
     * @ApiSecurity(name="apiToken")
     */
    public function pingAction(): JsonResponse
    {
        return $this->jsonResponse([
            'status' => 'ok',
            'plugin' => 'LhgPayrollBundle',
        ]);
    }

    /**
     * @Route(path="/api/payroll/statuses", name="api_payroll_statuses", methods={"GET"})
     *
     * @SWG\Get(
     *     tags={"LHG Payroll API"},
     *     summary="Returns payroll approval status codes"
     * )
     * @SWG\Response(
     *     response=200,
     *     description="List of status values and names",
     *     @SWG\Schema(
     *         type="object",
     *         @SWG\Property(property="code", type="integer", example=200),
     *         @SWG\Property(
     *             property="data",
     *             type="object",
     *             @SWG\Property(
     *                 property="statuses",
     *                 type="array",
     *                 @SWG\Items(
     *                     type="object",
     *                     @SWG\Property(property="value", type="integer", example=1),
     *                     @SWG\Property(property="name", type="string", example="PENDING")
     *                 )
     *             )
     *         )
     *     )
     * )
     *
     * @Security("is_granted('IS_AUTHENTICATED_REMEMBERED') and is_granted('api_payroll_view_own')")
     * @ApiSecurity(name="apiUser")
     * @ApiSecurity(name="apiToken")
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
     * @Route(path="/api/payroll/period", name="api_payroll_period", methods={"GET"})
     *
     * @SWG\Get(
     *     tags={"LHG Payroll API"},
     *     summary="Returns biweekly period start and end for a reference date",
     *     @SWG\Parameter(
     *         name="date",
     *         in="query",
     *         type="string",
     *         required=false,
     *         description="Reference date (Y-m-d), defaults to today"
     *     )
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Biweekly period boundaries",
     *     @SWG\Schema(
     *         type="object",
     *         @SWG\Property(property="code", type="integer", example=200),
     *         @SWG\Property(
     *             property="data",
     *             type="object",
     *             @SWG\Property(
     *                 property="period",
     *                 type="object",
     *                 @SWG\Property(property="selectedDate", type="string", example="2026-06-01"),
     *                 @SWG\Property(property="start", type="string", example="2026-05-18"),
     *                 @SWG\Property(property="end", type="string", example="2026-05-31")
     *             )
     *         )
     *     )
     * )
     *
     * @Security("is_granted('IS_AUTHENTICATED_REMEMBERED') and is_granted('api_payroll_view_own')")
     * @ApiSecurity(name="apiUser")
     * @ApiSecurity(name="apiToken")
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
     * @Route(path="/api/payroll/biweekly", name="api_payroll_biweekly", methods={"GET"})
     *
     * @SWG\Get(
     *     tags={"LHG Payroll API"},
     *     summary="Returns biweekly payroll data for a user",
     *     @SWG\Parameter(
     *         name="date",
     *         in="query",
     *         type="string",
     *         required=false,
     *         description="Reference date (Y-m-d)"
     *     ),
     *     @SWG\Parameter(
     *         name="user_id",
     *         in="query",
     *         type="integer",
     *         required=false,
     *         description="Target user ID (team lead / super admin only; omit for self)"
     *     )
     * )
     * @SWG\Response(response=200, description="Biweekly payroll detail")
     * @SWG\Response(response=403, description="Access denied")
     *
     * @Security("is_granted('IS_AUTHENTICATED_REMEMBERED') and is_granted('api_payroll_view_own')")
     * @ApiSecurity(name="apiUser")
     * @ApiSecurity(name="apiToken")
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
     * @Route(path="/api/payroll/queues", name="api_payroll_queues", methods={"GET"})
     *
     * @SWG\Get(
     *     tags={"LHG Payroll API"},
     *     summary="Returns approval queues for the biweekly period",
     *     @SWG\Parameter(
     *         name="date",
     *         in="query",
     *         type="string",
     *         required=false,
     *         description="Reference date (Y-m-d)"
     *     )
     * )
     * @SWG\Response(response=200, description="Submitted, approved, and not-submitted lists")
     * @SWG\Response(response=403, description="Access denied")
     *
     * @Security("is_granted('IS_AUTHENTICATED_REMEMBERED') and is_granted('api_payroll_view_own')")
     * @ApiSecurity(name="apiUser")
     * @ApiSecurity(name="apiToken")
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

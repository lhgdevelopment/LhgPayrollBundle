<?php

namespace KimaiPlugin\LhgPayrollBundle\Controller\API;

use App\API\BaseApiController;
use App\Entity\User;
use App\Entity\UserPreference;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\LhgPayrollBundle\Service\TeamLeadAndFinanceService;
use Nelmio\ApiDocBundle\Annotation\Security as ApiSecurity;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security as SecurityFacade;

class PayrollUserApiController extends BaseApiController
{
    private $entityManager;
    private $teamLeadAndFinanceService;
    private $security;

    public function __construct(
        EntityManagerInterface $entityManager,
        TeamLeadAndFinanceService $teamLeadAndFinanceService,
        SecurityFacade $security
    ) {
        $this->entityManager = $entityManager;
        $this->teamLeadAndFinanceService = $teamLeadAndFinanceService;
        $this->security = $security;
    }

    /**
     * @Route(path="/api/payroll/users", name="api_payroll_users", methods={"GET"})
     *
     * @SWG\Get(
     *     tags={"LHG Payroll API"},
     *     summary="Returns users with non-zero hourly rates"
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Users with hourly rates",
     *     @SWG\Schema(
     *         type="object",
     *         @SWG\Property(property="code", type="integer", example=200),
     *         @SWG\Property(property="message", type="string"),
     *         @SWG\Property(
     *             property="data",
     *             type="array",
     *             @SWG\Items(
     *                 type="object",
     *                 @SWG\Property(property="id", type="integer", example=1),
     *                 @SWG\Property(property="username", type="string"),
     *                 @SWG\Property(property="email", type="string"),
     *                 @SWG\Property(property="name", type="string"),
     *                 @SWG\Property(property="hourly_rate", type="string", example="75.00")
     *             )
     *         )
     *     )
     * )
     *
     * @Security("is_granted('IS_AUTHENTICATED_REMEMBERED') and (is_granted('view_user') or is_granted('api_payroll_view_all'))")
     * @ApiSecurity(name="apiUser")
     * @ApiSecurity(name="apiToken")
     */
    public function usersAction(): JsonResponse
    {
        $usersData = $this->entityManager->createQueryBuilder()
            ->select('u.id', 'u.username', 'u.email', 'u.alias as name', 'up.value as hourly_rate')
            ->from(User::class, 'u')
            ->leftJoin(UserPreference::class, 'up', 'WITH', 'up.user = u.id AND up.name = :preferenceName')
            ->where('up.value IS NOT NULL AND up.value != 0')
            ->setParameter('preferenceName', 'hourly_rate')
            ->getQuery()
            ->getResult();

        return new JsonResponse([
            'code' => 200,
            'message' => 'User data retrieved successfully.',
            'data' => $usersData,
        ], Response::HTTP_OK);
    }

    /**
     * @Route(path="/api/payroll/users/accessible", name="api_payroll_users_accessible", methods={"GET"})
     *
     * @SWG\Get(
     *     tags={"LHG Payroll API"},
     *     summary="Returns users the current actor may view payroll for"
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Accessible users (self, team, or all)",
     *     @SWG\Schema(
     *         type="object",
     *         @SWG\Property(property="code", type="integer", example=200),
     *         @SWG\Property(
     *             property="data",
     *             type="object",
     *             @SWG\Property(
     *                 property="users",
     *                 type="array",
     *                 @SWG\Items(
     *                     type="object",
     *                     @SWG\Property(property="id", type="integer"),
     *                     @SWG\Property(property="username", type="string"),
     *                     @SWG\Property(property="alias", type="string"),
     *                     @SWG\Property(property="email", type="string"),
     *                     @SWG\Property(property="hourly_rate", type="string"),
     *                     @SWG\Property(property="salary", type="string")
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
    public function accessibleUsersAction(): JsonResponse
    {
        $users = $this->teamLeadAndFinanceService->getTeamUsers();
        $currentUser = $this->security->getUser();
        if ($users === [] && $currentUser instanceof User) {
            $users = [$currentUser];
        }

        $data = [];
        foreach ($users as $user) {
            $data[] = [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'alias' => $user->getAlias(),
                'email' => $user->getEmail(),
                'hourly_rate' => $user->getPreferenceValue('hourly_rate'),
                'salary' => $user->getPreferenceValue('lhg_payroll.payroll.salary'),
            ];
        }

        return new JsonResponse([
            'code' => 200,
            'data' => ['users' => $data],
        ]);
    }
}

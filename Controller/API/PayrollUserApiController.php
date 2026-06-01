<?php

namespace KimaiPlugin\LhgPayrollBundle\Controller\API;

use App\API\BaseApiController;
use App\Entity\User;
use App\Entity\UserPreference;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\LhgPayrollBundle\Service\TeamLeadAndFinanceService;
use Nelmio\ApiDocBundle\Annotation\Security as ApiSecurity;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Security\Core\Security as SecurityFacade;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(path="/api/payroll")
 * @SWG\Tag(name="LHG Payroll Users")
 *
 * @Security("is_granted('IS_AUTHENTICATED_REMEMBERED')")
 * @ApiSecurity(name="apiUser")
 * @ApiSecurity(name="apiToken")
 */
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
     * Returns users with hourly rates (for payroll integrations / MCP).
     *
     * @Route(path="/users", name="api_payroll_users", methods={"GET"})
     * @Security("is_granted('view_user') or is_granted('api_payroll_view_all')")
     * @SWG\Response(response=200, description="Users with non-zero hourly rates")
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
     * Users the current actor may view payroll for (self, team, or all).
     *
     * @Route(path="/users/accessible", name="api_payroll_users_accessible", methods={"GET"})
     * @Security("is_granted('api_payroll_view_own')")
     * @SWG\Response(response=200, description="Users accessible for payroll context")
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

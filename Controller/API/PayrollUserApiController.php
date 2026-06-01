<?php

namespace KimaiPlugin\LhgPayrollBundle\Controller\API;

use App\API\BaseApiController;
use App\Entity\User;
use App\Entity\UserPreference;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\LhgPayrollBundle\Service\TeamLeadAndFinanceService;
use Nelmio\ApiDocBundle\Attribute\Security as ApiSecurity;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/payroll')]
#[OA\Tag(name: 'LHG Payroll Users')]
#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
#[ApiSecurity(name: 'apiUser')]
#[ApiSecurity(name: 'apiToken')]
class PayrollUserApiController extends BaseApiController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TeamLeadAndFinanceService $teamLeadAndFinanceService,
        private readonly Security $security
    ) {
    }

    #[Route(path: '/users', name: 'api_payroll_users', methods: ['GET'])]
    #[IsGranted(new Expression('is_granted("view_user") or is_granted("api_payroll_view_all")'))]
    #[OA\Response(response: 200, description: 'Users with non-zero hourly rates')]
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

    #[Route(path: '/users/accessible', name: 'api_payroll_users_accessible', methods: ['GET'])]
    #[IsGranted('api_payroll_view_own')]
    #[OA\Response(response: 200, description: 'Users accessible for payroll context')]
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

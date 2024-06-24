<?php 

namespace KimaiPlugin\LhgPayrollBundle\Controller;

use App\API\BaseApiController;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Nelmio\ApiDocBundle\Annotation\Security as ApiSecurity;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Swagger\Annotations as SWG;
use App\Entity\User;
use App\Entity\UserPreference;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route("/api/payroll")
 * @SWG\Tag(name="Payroll")
 *
 * @Security("is_granted('IS_AUTHENTICATED_REMEMBERED')")
 */
class UserListController extends BaseApiController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Returns a list of users with their hourly rates, excluding users with hourly rate 0 or null.
     *
     * @SWG\Response(
     *     response=200,
     *     description="Returns the list of users with hourly rates",
     *     @SWG\Schema(
     *         type="array",
     *         @SWG\Items(
     *             type="object",
     *             @SWG\Property(property="id", type="integer"),
     *             @SWG\Property(property="username", type="string"),
     *             @SWG\Property(property="email", type="string"),
     *             @SWG\Property(property="alias", type="string"),
     *             @SWG\Property(property="hourly_rate", type="string", description="User's hourly rate")
     *         )
     *     )
     * )
     * @SWG\Response(
     *     response=403,
     *     description="Access denied"
     * )
     * @SWG\Response(
     *     response=500,
     *     description="Internal server error"
     * )
     *
     * @Security("is_granted('view_user')")
     * @ApiSecurity(name="apiUser")
     * @ApiSecurity(name="apiToken")
     *
     * @Route("/users", methods={"GET"}, name="get_user_hourly_rates")
     */
    public function cgetAction(): JsonResponse
    {
        try {
            // Constructing the query
            $queryBuilder = $this->entityManager->createQueryBuilder();

            $usersData = $queryBuilder
                ->select('u.id', 'u.username', 'u.email', 'u.alias as name', 'up.value as hourly_rate')
                ->from(User::class, 'u')
                ->leftJoin(UserPreference::class, 'up', 'WITH', 'up.user = u.id AND up.name = :preferenceName')
                ->where('up.value IS NOT NULL AND up.value != 0')
                ->setParameter('preferenceName', 'hourly_rate')
                ->getQuery()
                ->getResult();

            // Format response data
            $response = [
                'code' => 200,
                'message' => 'User data retrieved successfully.',
                'data' => $usersData,
            ];

            return new JsonResponse($response, Response::HTTP_OK);

        } catch (\Exception $e) {
            // Handle exception and return error response
            $response = [
                'code' => 500,
                'message' => 'An error occurred while retrieving user data.',
                'error' => $e->getMessage(),
            ];

            return new JsonResponse($response, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

<?php 
namespace KimaiPlugin\LhgPayrollBundle\Controller;

use App\Repository\Query\UserQuery;
use App\Utils\SearchTerm;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use KimaiPlugin\LhgPayrollBundle\Entity\Speciality;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use App\Entity\Project;
use App\Entity\Timesheet;
use App\Entity\User;

/**
 * @Route(path="/admin/talent")
 */
class TalentController extends AbstractController
{
    /**
     * @Route(path="/", name="talent_index", defaults={"page": 1}, methods={"GET"})
     * @Route(path="/page/{page}", requirements={"page": "[1-9]\d*"}, name="talent_index_paginated", methods={"GET"})
     */
    public function index(Request $request, int $page = 1): Response
    {
        $query = new UserQuery();
        $query->setPage($page);
        $query->setPageSize(25);

        // Store search term in query object
        $searchString = $request->query->get('searchTerm');
        if (!empty($searchString)) {
            $searchTerm = new SearchTerm($searchString);
            $query->setSearchTerm($searchTerm);
        }

        $userRepository = $this->getDoctrine()->getRepository(User::class);
        $talents = $userRepository->getPagerfantaForQuery($query);

        // If the requested page is beyond the last page, redirect to page 1
        try {
            $talents->setCurrentPage($page);
        } catch (\OutOfRangeException $e) {
            $routeParams = ['page' => 1];
            if (!empty($searchString)) {
                $routeParams['searchTerm'] = $searchString;
            }
            return $this->redirectToRoute('talent_index_paginated', $routeParams);
        }

        $routeParams = [
            'page' => $page
        ];
        if (!empty($searchString)) {
            $routeParams['searchTerm'] = $searchString;
        }

        return $this->render('@LhgPayroll/talent/index.html.twig', [
            'talents' => $talents,
            'query' => $query,
            'searchTerm' => $searchString,
            'routeParams' => $routeParams
        ]);
    }

    /**
     * @Route(path="/{id}", name="talent_view", methods={"GET"})
     */
    public function view(User $talent): Response
    {
        // Get all timesheets for the user
        $timesheetRepository = $this->getDoctrine()->getRepository(Timesheet::class);
        $timesheets = $timesheetRepository->findBy(['user' => $talent]);

        // Get unique projects from timesheets
        $projects = [];
        foreach ($timesheets as $timesheet) {
            $projectId = $timesheet->getProject()->getId();
            if (!isset($projects[$projectId])) {
                $projects[$projectId] = $timesheet->getProject();
            }
        }

        // Get user's specialities using raw query
        $connection = $this->getDoctrine()->getConnection();
        $specialityQuery = '
            SELECT s.* 
            FROM lhg_speciality s
            INNER JOIN lhg_user_speciality us ON s.id = us.speciality_id
            WHERE us.user_id = :user_id
            ORDER BY s.name
        ';
        $specialityStmt = $connection->prepare($specialityQuery);
        $specialityResult = $specialityStmt->executeQuery(['user_id' => $talent->getId()]);
        $specialities = $specialityResult->fetchAllAssociative();

        return $this->render('@LhgPayroll/talent/view.html.twig', [
            'talent' => $talent,
            'projects' => array_values($projects),
            'specialities' => $specialities
        ]);
    }

    /**
     * @Route(path="/{id}/specialities", name="talent_specialities", methods={"GET", "POST"})
     */
    public function specialities(Request $request, $id): Response
    {
        $connection = $this->getDoctrine()->getConnection();
        
        // Get user
        $userQuery = 'SELECT * FROM kimai2_users WHERE id = :id';
        $userStmt = $connection->prepare($userQuery);
        $userResult = $userStmt->executeQuery(['id' => $id]);
        $talent = $userResult->fetchAssociative();
        
        if (!$talent) {
            throw $this->createNotFoundException('User not found');
        }

        // Get all specialities
        $specialityQuery = 'SELECT * FROM lhg_speciality ORDER BY name';
        $specialityStmt = $connection->prepare($specialityQuery);
        $specialityResult = $specialityStmt->executeQuery();
        $allSpecialities = $specialityResult->fetchAllAssociative();

        // Get user's current specialities
        $userSpecialitiesQuery = 'SELECT speciality_id FROM lhg_user_speciality WHERE user_id = :user_id';
        $userSpecialitiesStmt = $connection->prepare($userSpecialitiesQuery);
        $userSpecialitiesResult = $userSpecialitiesStmt->executeQuery(['user_id' => $id]);
        $currentSpecialities = array_column($userSpecialitiesResult->fetchAllAssociative(), 'speciality_id');

        if ($request->isMethod('POST')) {
            // Get selected specialities from form
            $selectedSpecialities = $request->request->all()['specialities'] ?? [];
            
            try {
                // Start transaction
                $connection->beginTransaction();
                
                // Delete all current specialities for user
                $deleteQuery = 'DELETE FROM lhg_user_speciality WHERE user_id = :user_id';
                $deleteStmt = $connection->prepare($deleteQuery);
                $deleteStmt->executeQuery(['user_id' => $id]);
                
                // Insert new specialities
                if (!empty($selectedSpecialities)) {
                    $insertQuery = 'INSERT INTO lhg_user_speciality (user_id, speciality_id) VALUES (:user_id, :speciality_id)';
                    $insertStmt = $connection->prepare($insertQuery);
                    
                    foreach ($selectedSpecialities as $specialityId) {
                        $insertStmt->executeQuery([
                            'user_id' => $id,
                            'speciality_id' => $specialityId
                        ]);
                    }
                }
                
                $connection->commit();
                $this->addFlash('success', 'Specialities updated successfully');
                
                return $this->redirectToRoute('talent_view', ['id' => $id]);
                
            } catch (\Exception $e) {
                $connection->rollBack();
                $this->addFlash('error', 'Error updating specialities: ' . $e->getMessage());
            }
        }

        return $this->render('@LhgPayroll/talent/specialities.html.twig', [
            'talent' => $talent,
            'specialities' => $allSpecialities,
            'currentSpecialities' => $currentSpecialities
        ]);
    }
}
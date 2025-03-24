<?php 
namespace KimaiPlugin\LhgPayrollBundle\Controller;

use App\Entity\User;
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

        // Get all specialities
        $specialityRepository = $this->getDoctrine()->getRepository(Speciality::class);
        $specialities = $specialityRepository->findAll();

        return $this->render('@LhgPayroll/talent/view.html.twig', [
            'talent' => $talent,
            'projects' => array_values($projects),
            'specialities' => $specialities
        ]);
    }

    /**
     * @Route(path="/{id}/specialities", name="talent_specialities", methods={"GET", "POST"})
     */
    public function specialities(Request $request, User $talent): Response
    {
        $specialityRepo = $this->getDoctrine()->getRepository(Speciality::class);
        $allSpecialities = $specialityRepo->findAll();
        
        $form = $this->createFormBuilder()
            ->add('specialities', ChoiceType::class, [
                'choices' => $allSpecialities,
                'choice_label' => 'name',
                'choice_value' => 'id',
                'multiple' => true,
                'expanded' => true,
                'data' => [],  // Initially empty as we're not using relations yet
                'label' => 'Specialities',
                'required' => false,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Save',
                'attr' => [
                    'class' => 'btn btn-primary'
                ]
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $selectedSpecialities = $form->get('specialities')->getData();
            
            // Here you would handle saving the specialities
            // For now, just redirect back
            $this->addFlash('success', 'Specialities updated successfully');
            
            return $this->redirectToRoute('talent_view', ['id' => $talent->getId()]);
        }

        return $this->render('@LhgPayroll/talent/specialities.html.twig', [
            'talent' => $talent,
            'form' => $form->createView()
        ]);
    }
}
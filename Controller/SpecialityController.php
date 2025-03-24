<?php

namespace KimaiPlugin\LhgPayrollBundle\Controller;

use App\Controller\AbstractController;
use KimaiPlugin\LhgPayrollBundle\Entity\Speciality;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

/**
 * @Route(path="/admin/speciality")
 */
class SpecialityController extends AbstractController
{
    /**
     * @Route(path="", name="speciality_list", methods={"GET"})
     */
    public function list(): Response
    {
        $specialities = $this->getDoctrine()
            ->getRepository(Speciality::class)
            ->findAll();

        return $this->render('@LhgPayroll/speciality/list.html.twig', [
            'specialities' => $specialities
        ]);
    }

    /**
     * @Route(path="/create", name="speciality_create", methods={"GET", "POST"})
     */
    public function create(Request $request): Response
    {
        $speciality = new Speciality();
        $form = $this->createFormBuilder($speciality)
            ->add('name', TextType::class)
            ->add('description', TextareaType::class, [
                'required' => false
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Create'
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($speciality);
            $entityManager->flush();

            $this->addFlash('success', 'Speciality created successfully');
            return $this->redirectToRoute('speciality_list');
        }

        return $this->render('@LhgPayroll/speciality/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Create Speciality'
        ]);
    }

    /**
     * @Route(path="/{id}/edit", name="speciality_edit", methods={"GET", "POST"})
     */
    public function edit(Request $request, Speciality $speciality): Response
    {
        $form = $this->createFormBuilder($speciality)
            ->add('name', TextType::class)
            ->add('description', TextareaType::class, [
                'required' => false
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Update'
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();
            $this->addFlash('success', 'Speciality updated successfully');
            return $this->redirectToRoute('speciality_list');
        }

        return $this->render('@LhgPayroll/speciality/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Edit Speciality'
        ]);
    }

    /**
     * @Route(path="/{id}/delete", name="speciality_delete", methods={"GET"})
     */
    public function delete(Request $request, Speciality $speciality): Response
    {
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->remove($speciality);
        $entityManager->flush();

        $this->flashSuccess('Speciality deleted successfully');

        return $this->redirectToRoute('speciality_list');
    }
} 
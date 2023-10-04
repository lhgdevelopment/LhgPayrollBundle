<?php 
// src/KimaiPlugin/LhgPayrollBundle/Controller/VendorController.php
namespace KimaiPlugin\LhgPayrollBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use KimaiPlugin\LhgPayrollBundle\Entity\Vendor;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

/**
 * @Route(path="/admin/vendor")
 */
class VendorController extends AbstractController
{
    /**
     * @Route("/", name="vendor_index", methods={"GET"})
     */
    public function index(): Response
    {
        $vendors = $this->getDoctrine()->getRepository(Vendor::class)->findAll();

        return $this->render('@LhgPayroll/vendor/index.html.twig', [
            'vendors' => $vendors,
        ]);
    }

    /**
     * @Route("/new", name="vendor_new", methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        $vendor = new Vendor();
        $form = $this->createFormBuilder($vendor)
            ->add('name')
            ->add('website')
            ->add('save', SubmitType::class, ['label' => 'Create Vendor'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($vendor);
            $entityManager->flush();

            return $this->redirectToRoute('vendor_index');
        }

        return $this->render('@LhgPayroll/vendor/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="vendor_show", methods={"GET"})
     */
    public function show(Vendor $vendor): Response
    {
        return $this->render('@LhgPayroll/vendor/show.html.twig', [
            'vendor' => $vendor,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="vendor_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Vendor $vendor): Response
    {
        $form = $this->createFormBuilder($vendor)
            ->add('name')
            ->add('website')
            ->add('save', SubmitType::class, ['label' => 'Update Vendor'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('vendor_index');
        }

        return $this->render('@LhgPayroll/vendor/edit.html.twig', [
            'vendor' => $vendor,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="vendor_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Vendor $vendor): Response
    {
        if ($this->isCsrfTokenValid('delete'.$vendor->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($vendor);
            $entityManager->flush();
        }

        return $this->redirectToRoute('vendor_index');
    }
}

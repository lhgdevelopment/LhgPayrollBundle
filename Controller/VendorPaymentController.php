<?php 

// src/KimaiPlugin/LhgPayrollBundle/Controller/VendorPaymentController.php

namespace KimaiPlugin\LhgPayrollBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use KimaiPlugin\LhgPayrollBundle\Entity\VendorPayment;
use KimaiPlugin\LhgPayrollBundle\Form\VendorPaymentType;

/**
 * @Route("/admin/vendor-payment")
 */
class VendorPaymentController extends AbstractController
{
    /**
     * @Route("/", name="vendor_payment_index", methods={"GET"})
     */
    public function index(): Response
    {
        $vendorPayments = $this->getDoctrine()
            ->getRepository(VendorPayment::class)
            ->findAll();

        return $this->render('@LhgPayroll/vendor_payment/index.html.twig', [
            'vendor_payments' => $vendorPayments,
        ]);
    }

    /**
     * @Route("/new", name="vendor_payment_new", methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        $vendorPayment = new VendorPayment();
        $form = $this->createForm(VendorPaymentType::class, $vendorPayment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();

            $vendor = $form->get('vendor')->getData();
            $vendorPayment->setVendor($vendor);

            $project = $form->get('project')->getData();
            $vendorPayment->setProject($project);
            $entityManager->persist($vendorPayment);
            $entityManager->flush();

            return $this->redirectToRoute('vendor_payment_index');
        }

        return $this->render('@LhgPayroll/vendor_payment/new.html.twig', [
            'vendor_payment' => $vendorPayment,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="vendor_payment_show", methods={"GET"})
     */
    public function show(VendorPayment $vendorPayment): Response
    {
        return $this->render('@LhgPayroll/vendor_payment/show.html.twig', [
            'vendor_payment' => $vendorPayment,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="vendor_payment_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, VendorPayment $vendorPayment): Response
    {
        $form = $this->createForm(VendorPaymentType::class, $vendorPayment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('vendor_payment_index');
        }

        return $this->render('@LhgPayroll/vendor_payment/edit.html.twig', [
            'vendor_payment' => $vendorPayment,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="vendor_payment_delete", methods={"DELETE"})
     */
    public function delete(Request $request, VendorPayment $vendorPayment): Response
    {
        if ($this->isCsrfTokenValid('delete'.$vendorPayment->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($vendorPayment);
            $entityManager->flush();
        }

        return $this->redirectToRoute('vendor_payment_index');
    }
}

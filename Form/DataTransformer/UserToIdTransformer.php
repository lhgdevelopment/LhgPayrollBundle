<?php

namespace KimaiPlugin\LhgPayrollBundle\Form\DataTransformer;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class UserToIdTransformer implements DataTransformerInterface
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function transform($user)
    {
        dump(['Transformer' => $user]);
        if (null === $user) {
            return '';
        }

        return $user;

        // return $user->getId();
    }

    public function reverseTransform($userId)
    {
        dump(['Reverse Transformer' => $userId]);
        if (!$userId) {
            return null;
        }

        $user = $this->entityManager
            ->getRepository(User::class)
            ->find($userId);

        if (null === $user) {
            throw new TransformationFailedException(sprintf(
                'A user with ID "%s" does not exist!',
                $userId
            ));
        }

        return $user;
    }
}

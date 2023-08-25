<?php 

namespace KimaiPlugin\LhgPayrollBundle\Service;

use App\Repository\UserRepository; 

class UserService 
{
    private $repository;
    public function __construct(UserRepository $repository){
        $this->repository = $repository;
    }
    public function getAllUsers(): array{

        $returnData = [];
        $users = $this->repository->findAll();
        
        foreach ($users as $key => $user) {
            $returnData[$user->getDisplayName()] = $user->getId(); 
        }

        return $returnData;
    }
}
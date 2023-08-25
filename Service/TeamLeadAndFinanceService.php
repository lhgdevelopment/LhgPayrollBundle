<?php 

namespace KimaiPlugin\LhgPayrollBundle\Service;

use App\Entity\User;
use App\Entity\UserPreference;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security; 

class TeamLeadAndFinanceService 
{
    private $userRepository;
    private $entityManager;
    private $security;
    private $user;
    public function __construct(
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        Security $security){
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
        $this->security = $security;
        $this->user = $security->getUser();
    }
    
    public function isTeamLead() : bool{
        $user = $this->security->getUser();
        $userPreferenceRepo = $this->entityManager->getRepository(UserPreference::class);
        $teamMember = $userPreferenceRepo->findOneBy([
            'name' => 'lhg_payroll.approvval_flow.team_lead',
            'value' =>$user->getId()
        ]);

        if($teamMember){
            return true;
        }

        return false;
    }

    public function isAdmin() : bool {
        if($this->security->isGranted('ROLE_SUPER_ADMIN')){
            return true;
        }
        return false;
    }

    public function getTeamUsers(){
        $users = [];

        if($this->isAdmin()){
            $users =  $this->userRepository->findAll();
        }
        if($this->isTeamLead()){
            $userPreferenceRepo = $this->entityManager->getRepository(UserPreference::class);
    
            $preferences = $userPreferenceRepo->findBy([
                'name' => 'lhg_payroll.approvval_flow.team_lead',
                'value' => $this->user->getId(),
            ]);
            
            foreach ($preferences as $preference) {
                $user = $preference->getUser();
                
                if ($user instanceof User) {
                    $users[] = $user;
                }
            }

            if(sizeof($users) > 0){
                array_push($users, $this->user);
            }
        } 

        return $users;
    }

    public function isInTeam($userId) {
        $teamMembers = $this->getTeamUsers(); 
        foreach ($teamMembers as $teamMember) {  
            if ($teamMember->getId() === (int) $userId) { 
                return true;
            }
        }

        return false;
    }

}
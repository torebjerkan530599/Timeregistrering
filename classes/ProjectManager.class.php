<?php


use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

class ProjectManager
{
    private $db;
    private $request;
    private $session;

    //CONSTRUCTOR//
    function __construct(PDO $db, Request $request, Session $session)
    {
        $this->db = $db;
        $this->request = $request;
        $this->session = $session;
    }

    /////////////////////////////////////////////////////////////////////////////
    /// ERROR
    /////////////////////////////////////////////////////////////////////////////

    private function NotifyUser($strHeader, $strMessage = "")
    {
        //$this->session->getFlashBag()->clear();
        $this->session->getFlashBag()->add('header', $strHeader);
        $this->session->getFlashBag()->add('message', $strMessage);
    }

    /////////////////////////////////////////////////////////////////////////////
    /// PROJECTS
    /////////////////////////////////////////////////////////////////////////////

    // GET ALL PROJECTS
    public function getAllProjects(): array
    {
        try {
            $stmt = $this->db->prepare("SELECT Projects.*, Users.username, Users.firstName, Users.lastName
FROM Projects
LEFT JOIN Users ON Users.userID = Projects.projectLeader WHERE 1 ORDER BY `startTime` DESC;");
            $stmt->execute();
            if ($projects = $stmt->fetchAll(PDO::FETCH_ASSOC)) {
                return $projects;
            } else {
                $this->notifyUser("Projects not found", "Kunne ikke hente prosjekter");
                //return new Project();
                return array();
            }
        } catch (Exception $e) {
            $this->NotifyUser("En feil oppstod, på getAllProjects()", $e->getMessage());
            //return new Project();
            return array();
        }
    }

    //GET PROJECT
    public function getProject(int $projectID)
    {
        try {
            $stmt = $this->db->prepare(query: "SELECT * FROM Projects WHERE projectID = :projectID;");
            $stmt->bindParam(':projectID', $projectID, PDO::PARAM_INT, 100);
            $stmt->execute();
            if ($project = $stmt->fetchObject("Project")) {
                return $project;
            } else {
                $this->notifyUser("Ingen prosjekt funnet med dette navnet.", "Kunne ikke hente prosjektet.");
                return null;
            }
        } catch (Exception $e) {
            $this->NotifyUser("En feil oppstod, på getProject()", $e->getMessage());
            return null;
        }
    }



    // ADD PROJECT
    public function addProject(): bool
    {
        $isAcceptedByAdmin = $this->session->get('User')->isAdmin() ? 1:0;
        $projectName = $this->request->request->get('projectName');

        //Ungå initsialisering av 01.01.1970 00:00:00 om starttid og slutttid ikke er lagt inn av bruker.
        $dateTime1 = $this->request->request->get('startTime');
        $dateTime2 = $this->request->request->get('finishTime');
        if ($dateTime1 != null) {
            $dateTimeStr1 = date('Y-m-d\TH:i:s', strtotime($dateTime1));
            $startTime = $dateTimeStr1;
        } else {
            $startTime = $dateTime1;
        }

        if ($dateTime2 != null) {
            $dateTimeStr2 = date('Y-m-d\TH:i:s', strtotime($dateTime2));
            $finishTime = $dateTimeStr2;
        } else {
            $finishTime = $dateTime2;
        }
        $status = $this->request->request->get('status');
        $customer = $this->request->request->get('customer');

        try {
            $stmt = $this->db->prepare(
                query: "insert into Projects (projectName, startTime, finishTime, status, customer, isAcceptedByAdmin) 
                values (:projectName, :startTime, :finishTime, :status, :customer, :isAcceptedByAdmin);");
            $stmt->bindParam(':projectName', $projectName, PDO::PARAM_STR);
            $stmt->bindParam(':startTime', $startTime, PDO::PARAM_STR);
            $stmt->bindParam(':finishTime', $finishTime, PDO::PARAM_STR);
            $stmt->bindParam(':status', $status, PDO::PARAM_INT);
            $stmt->bindParam(':customer', $customer, PDO::PARAM_INT);
            $stmt->bindParam(':isAcceptedByAdmin', $isAcceptedByAdmin, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->rowCount() == 1) {
                $this->notifyUser("Nytt prosjekt ble opprettet", "Fullført!");

                return true;
            } else {
                $this->notifyUser("Feil ved opprettelse av nytt prosjekt");
                return false;
            }
        } catch (Exception $e) {
            $this->notifyUser("Feil ved opprettelse av nytt prosjekt!", $e->getMessage());
            return false;
        }
    }



    //EDIT PROJECT
    public function editProject(Project $project): bool
    {
        $projectName = $project->getProjectName();
        $projectLeader = $this->request->request->get('projectLeader');
        $startTime = $this->request->request->get('startTime', $project->getStartTime());
        $finishTime = $this->request->request->get('finishTime', $project->getFinishTime());
        $status = $this->request->request->getInt('status', $project->getStatus());
        $customer = $this->request->request->getInt('customer', $project->getCustomer());
        $oldProjectLeader = $project->getProjectLeader();
        try {
            $stmt = $this->db->prepare(query: "update Projects set projectName = :projectName, projectLeader = :projectLeader, startTime = :startTime, 
                        finishTime = :finishTime, status = :status, customer = :customer 
                        WHERE projectName = :projectName;
                        UPDATE Users SET Users.isProjectLeader = 0
                        WHERE NOT EXISTS
                        (SELECT projectLeader FROM Projects WHERE projectLeader = :oldProjectLeader) AND Users.userID = :oldProjectLeader;
                        UPDATE Users SET Users.isProjectLeader = 1 WHERE Users.userID = :projectLeader;");
            $stmt->bindParam(':projectName', $projectName);
            $stmt->bindParam(':projectLeader', $projectLeader, PDO::PARAM_INT);
            $stmt->bindParam(':startTime', $startTime);
            $stmt->bindParam(':finishTime', $finishTime);
            $stmt->bindParam(':status', $status, PDO::PARAM_INT);
            $stmt->bindParam(':customer', $customer, PDO::PARAM_INT);
            $stmt->bindParam(':oldProjectLeader', $oldProjectLeader, PDO::PARAM_INT);
            if ($stmt->execute()) {
                $stmt->closeCursor();
                $this->notifyUser('Project details changed');
                return true;
            } else {
                $this->notifyUser('Failed to change project details');
                return false;
            }
        } catch (Exception $e) {
            $this->notifyUser("Failed to change project details", $e->getMessage());
            return false;
        }
    }






/*
    public function getAllPotentialLeaders($projectId) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM Users WHERE ")
        }

    }*/
/*
    public function addEmployees($projectName)
    {
        $users = $this->request->request->get('projectMembers');
        try {
            $stmt = $this->db->prepare(query: "INSERT IGNORE INTO UsersAndProjects (userID, projectName) VALUES (:userID, :projectName);");
            if (is_array($users)) {
                foreach ($users as $userID) {
                    $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
                    $stmt->bindParam(':projectName', $projectName);
                    $stmt->execute();
                }
                $this->notifyUser("Medlemmer ble lagt til", '..........');
            } else {
                $this->notifyUser("Kunne ikke legge til medlemmer", '..........');
                return false;
            }
        } catch (Exception $e) {
            $this->notifyUser("Kunne ikke legge til medlemmer", $e->getMessage());
            return false;
        }
        return true;
    }*/


    public function removeEmployees(Project $project)
    {
        $users = $this->request->request->get('projectMembers');
        $projectName = $project->getProjectName();
        $projectLeader = $project->getProjectLeader();
        try {
            $stmt = $this->db->prepare(query: "DELETE FROM UsersAndProjects 
                    WHERE projectName = :projectName AND userId = :userID;
                    UPDATE Projects SET Projects.projectLeader = NULL 
                    WHERE projectName = :projectName AND Projects.projectLeader = :userID;
                    UPDATE Users SET Users.isProjectLeader = 0
                    WHERE NOT EXISTS
                    (SELECT projectLeader FROM Projects WHERE projectLeader = :projectLeader) AND Users.userID = :projectLeader;");
            if (is_array($users)) {
                foreach ($users as $userID) {
                    $stmt->bindParam(':projectName', $projectName);
                    $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
                    $stmt->bindParam(':projectLeader', $projectLeader, PDO::PARAM_INT);
                    $stmt->execute();
                    $stmt->closeCursor();
                }
            } else {
                $this->notifyUser("Failed to remove employee", '..........');
                return false;
            }
        } catch (Exception $e) {
            $this->notifyUser("Failed to remove employee", $e->getMessage());
            return false;
        }
        return true;
    }

    //GET MEMBERS
    public function getProjectMembers(string $projectName) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM Users WHERE EXISTS(SELECT UsersAndProjects.userID FROM UsersAndProjects WHERE UsersAndProjects.projectName = :projectName AND Users.userID = UsersAndProjects.userID);");
            $stmt->bindParam(':projectName', $projectName, PDO::PARAM_STR, 100);
            $stmt->execute();
            if ($members = $stmt->fetchAll(PDO::FETCH_CLASS, 'User')) {
                return $members;
            } else {
                $this->notifyUser("Ingen medlemmer funnet", "Kunne ikke hente medlemmer av prosjektet");
                return array();
            }
        } catch (Exception $e) {
            $this->NotifyUser("En feil oppstod, på getProjectMembers()", $e->getMessage());
            return array();
        }
    }

    public function addGroup($projectName): bool //returns boolean value
    {
        $groupName = $this->request->request->get('groupName');
        $isAdmin = $this->request->request->getInt('isAdmin', 0);
        try {
            $stmt = $this->db->prepare("INSERT INTO `Groups` (groupName, isAdmin, projectName)
              VALUES (:groupName, :isAdmin, :projectName);");
            $stmt->bindParam(':groupName', $groupName, PDO::PARAM_STR, 100);
            $stmt->bindParam(':isAdmin', $isAdmin, PDO::PARAM_INT, 100);
            $stmt->bindParam(':projectName', $projectName, PDO::PARAM_STR, 100);
            if ($stmt->execute()) {
                $groupId = $this->db->lastInsertId();
                if ($this->addEmployees($groupId)) {
                    $this->NotifyUser("En gruppe ble lagt til prosjektet");
                    return true;
                }
            } else {
                $this->NotifyUser("Feil ved å legge til gruppe");
                return false;
            }
        } catch (PDOException $e) {
            $this->NotifyUser("Feil ved å legge til gruppe", $e->getMessage());
            return false;
        }
    }

    public function getGroups($projectName) {
        $groups = array();
        try {
            $stmt = $this->db->prepare("SELECT Groups.*, count(UsersAndGroups.groupID) as nrOfUsers, Users.username, Users.firstName, Users.lastName
    FROM Groups
    JOIN UsersAndGroups ON Groups.groupID = UsersAndGroups.groupID
LEFT JOIN Users ON Groups.groupLeader = Users.userID
    WHERE Groups.projectName = :projectName GROUP BY Groups.groupID;");
            $stmt->bindParam(':projectName', $projectName, PDO::PARAM_STR);
            if ($stmt->execute()) {
                $groups = $stmt->fetchAll(PDO::FETCH_CLASS, "Group");
                return $groups;
            } else {
                $this->notifyUser("Feil i getGroups()");
                return $groups;
            }
        } catch (Exceptopn $e) {
            $this->notifyUser("Feil i getGroups()", $e->getMessage());
            return $groups;}
    }


    public function getLeadersCandidates(Group $group) : array
    {
        $candidates = array();
        $projectName = $group->getProjectName();
        $groupLeader = $group->getGroupLeader();
        $groupID = $group->getGroupID();
        try {
            $stmt = $this->db->prepare("SELECT * FROM Users WHERE EXISTS(SELECT UsersAndGroups.userID FROM UsersAndGroups WHERE UsersAndGroups.groupID = :groupID AND Users.userID = UsersAndGroups.userID)
                    AND NOT EXISTS (SELECT projectLeader FROM Projects WHERE Projects.projectName = :projectName);");
            $stmt->bindParam(':groupID', $groupID, PDO::PARAM_INT, 100);
            $stmt->bindParam(':projectName', $projectName, PDO::PARAM_STR);
            $stmt->bindParam(':groupLeader', $groupLeader, PDO::PARAM_INT);
            $stmt->execute();
            if ($members = $stmt->fetchAll(PDO::FETCH_CLASS, 'User')) {
                return $members;
            } else {
                $this->notifyUser("Ingen kandidater funnet", "Kunne ikke hente kandidater for gruppeleder");
                return array();
            }
        } catch (Exception $e) {
            $this->NotifyUser("En feil oppstod, på getLeaderCandidates()", $e->getMessage());
            return array();
        }
    }



    public function addEmployees($groupID)
    {
        $users = $this->request->request->get('groupMembers');
        try {
            $stmt = $this->db->prepare("INSERT IGNORE INTO UsersAndGroups (groupID, userID) VALUES (:groupID, :userID);");
            if (is_array($users)) {
                foreach ($users as $userID) {
                    $stmt->bindParam(':groupID', $groupID, PDO::PARAM_INT);
                    $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
                    $stmt->execute();
                }
                $this->notifyUser("Medlemmer ble lagt til");
            } else {
                $this->notifyUser("Fikk ikke legge til brukere");
                return false;
            }
        } catch (Exception $e) {
            $this->notifyUser("Fikk ikke legge til brukere", $e->getMessage());
            return false;
        }
        return true;
    }


    public function removeGroups(Project $project)
    {
        $users = $this->request->request->get('projectMembers');
        $projectName = $project->getProjectName();
        $projectLeader = $project->getProjectLeader();
        try {
            $stmt = $this->db->prepare(query: "DELETE FROM UsersAndProjects 
                    WHERE projectName = :projectName AND userId = :userID;
                    UPDATE Projects SET Projects.projectLeader = NULL 
                    WHERE projectName = :projectName AND Projects.projectLeader = :userID;
                    UPDATE Users SET Users.isProjectLeader = 0
                    WHERE NOT EXISTS
                    (SELECT projectLeader FROM Projects WHERE projectLeader = :projectLeader) AND Users.userID = :projectLeader;");
            if (is_array($users)) {
                foreach ($users as $userID) {
                    $stmt->bindParam(':projectName', $projectName);
                    $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
                    $stmt->bindParam(':projectLeader', $projectLeader, PDO::PARAM_INT);
                    $stmt->execute();
                    $stmt->closeCursor();
                }
            } else {
                $this->notifyUser("Failed to remove employee", '..........');
                return false;
            }
        } catch (Exception $e) {
            $this->notifyUser("Failed to remove employee", $e->getMessage());
            return false;
        }
        return true;
    }



    //DELETE PROJECT
    public function deleteProject(string $projectName)
    {
        $projectLeader = $this->request->request->get('projectLeader');
        try {
            $stmt = $this->db->prepare(query: "DELETE FROM Projects WHERE projectName = :projectName;
                                    UPDATE Users SET Users.isProjectLeader = 0
                                    WHERE NOT EXISTS
                                    (SELECT projectLeader FROM Projects WHERE projectLeader = :projectLeader) AND Users.userID = :projectLeader;");
            $stmt->bindParam(':projectName', $projectName, PDO::PARAM_STR);
            $stmt->bindParam(':projectLeader', $projectLeader, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->rowCount() >= 1) {
                $this->notifyUser("Project deleted", "Det var ikke noe svar fra databasen");
                return true;
            } else {
                $this->notifyUser("Failed to delete group!", "Det var ikke noe svar fra databasen");
                return false;
            }
        } catch (Exception $e) {
            $this->notifyUser("Failed to delete project!", $e->getMessage());
            return false;
        }
    }



    // ADD PHASE
    public function addPhase ($projectName) : bool
    {
        $phaseName = $this->request->request->get('phaseName');
        $startTime = $this->request->request->get('startTime');
        $finishTime = $this->request->request->get('finishTime');
        try{
            $sth = $this->db->prepare("insert into Phases (phaseName, projectName, startTime, finishTime, status) 
                values (:phaseName, :projectName, :startTime, :finishTime, 0);");
            $sth->bindParam(':phaseName', $phaseName, PDO::PARAM_STR);
            $sth->bindParam(':projectName', $projectName, PDO::PARAM_STR);
            $sth->bindParam(':startTime',  $startTime, PDO::PARAM_STR);
            $sth->bindParam(':finishTime', $finishTime, PDO::PARAM_STR);
            $sth->execute();
            if ($sth->rowCount() == 1) {
                $this->notifyUser("Ny fase ble lagt til");
                return true;
            } else {
                $this->notifyUser("Feil ve registrering av fase!");
                return false;
            }
        } catch (Exception $e) {
            $this->notifyUser("Feil ved registrering av fase!", $e->getMessage());
            return false;
        }
    }
    // END ADD PHASE


    // EDIT PHASE
    public function editPhase (Phase $phase) : bool
    {
        $phaseId = $phase->getPhaseID();
        $phaseName = $this->request->request->get('phaseName', $phase->getPhaseName());
        $startTime = $this->request->request->get('startTime', $phase->getStartTime());
        $finishTime = $this->request->request->get('finishTime', $phase->getFinishTime());
        $status = $this->request->request->getInt('status', $phase->getStatus());
        try{
            $sth = $this->db->prepare("update Phases set phaseName = :phaseName, startTime = :startTime, 
                  finishTime = :finishTime, status = :status where phaseID = :phaseID;");
            $sth->bindParam(':phaseName', $phaseName, PDO::PARAM_STR);
            $sth->bindParam(':startTime',  $startTime, PDO::PARAM_STR);
            $sth->bindParam(':finishTime', $finishTime, PDO::PARAM_STR);
            $sth->bindParam(':status', $status, PDO::PARAM_INT);
            $sth->bindParam(":phaseID", $phaseId, PDO::PARAM_INT);
            $sth->execute();
            if ($sth->rowCount() == 1) {
                $this->notifyUser("Fase ble endret");
                return true;
            } else {
                $this->notifyUser("Feil ve endring av fase!");
                return false;
            }
        } catch (Exception $e) {
            $this->notifyUser("Feil ved endring av fase!", $e->getMessage());
            return false;
        }
    }
    // END EDIT PHASE



    // DELETE PHASE
    public function deletePhase ($phaseId) : bool
    {
        try{
            $sth = $this->db->prepare("delete from Phases where phaseID = :phaseID;");
            $sth->bindParam(":phaseID", $phaseId, PDO::PARAM_INT);
            $sth->execute();
            if ($sth->rowCount() == 1) {
                $this->notifyUser("Fase ble slettet");
                return true;
            } else {
                $this->notifyUser("Feil ve sletting av fase!");
                return false;
            }
        } catch (Exception $e) {
            $this->notifyUser("Feil ved sletting av fase!", $e->getMessage());
            return false;
        }
    }
    // END DELETE PHASE


    // GET PHASE WITH TASKS
    /*public function getPhase ($phaseId) : array
    {
        try{
            $sth = $this->db->prepare("select * from Phases where projcetName = :projectName;");
            $sth->bindParam(":projectName", $projectName, PDO::PARAM_INT);
            $sth->execute();
            if ($sth->rowCount() == 1) {
                return true;
            } else {
                $this->notifyUser("Feil ve henting av faser!");
                return false;
            }
        } catch (Exception $e) {
            $this->notifyUser("Feil ved henting av faser!", $e->getMessage());
            return false;
        }
    }*/
    // END GET PHASE WITH TASKS



    // GET ALL PHASES
    public function getAllPhases ($projectName) : array
    {
        try{
            $sth = $this->db->prepare("select * from Phases where projcetName = :projectName;");
            $sth->bindParam(":projectName", $projectName, PDO::PARAM_INT);
            $sth->execute();
            if ($sth->rowCount() == 1) {
                return true;
            } else {
                $this->notifyUser("Feil ve henting av faser!");
                return false;
            }
        } catch (Exception $e) {
            $this->notifyUser("Feil ved henting av faser!", $e->getMessage());
            return false;
        }
    }
    // END GET ALL PHASES







    public function verifyProjectByAdmin($projectName) : bool {
        if($this->session->get('User')->isAdmin()) {
            try {
                $sth = $this->dbase->prepare("update Projects set isAcceptedByAdmin = 1 where projectName = :projectName");
                $sth->bindParam(':projectName', $projectName, PDO::PARAM_STR);
                $sth->execute();
                if($sth->rowCount() == 1) {
                    $this->notifyUser("Project verified by admin", "");
                    return true;
                } else {
                    $this->notifyUser("Failed to verify project", "");
                    return false;
                }
            } catch (Exception $e) {
                $this->notifyUser("Failed to verify project", $e->getMessage());
                return false;
            }
        } else {return false; }
    }

    //TODO
    public function getEmployees(Project $project)
    {
    }

    //TODO
    public function addCustomer(User $user, Project $project)
    {
    }

    //TODO
    public function getCustomers(Project $project)
    {
    }
}
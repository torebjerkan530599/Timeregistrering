<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

class TaskManager {
    private $db;
    private $request;
    private $session;

    //CONSTRUCTOR//
    function __construct(PDO $db, Request $request, Session $session) {
        $this->db = $db;
        $this->request = $request;
        $this->session = $session;
    }

    private function NotifyUser($strHeader, $strMessage = null) {
        $this->session->getFlashBag()->clear();
        $this->session->getFlashBag()->add('header', $strHeader);
        $this->session->getFlashBag()->add('message', $strMessage);
    }

    // GET ALL TASKS
    public function getAllTasks($hasSubtask = null, $projectName = null, $phaseID = null, $groupID = null, $status = null,
                                $mainResponsible = null, $parentTask = null, $orderBy = null) : array {
        $tasks = array();
        $query = 'SELECT Tasks.*, CONCAT(mainResponsible.firstName, " ", mainResponsible.lastName, " (", mainResponsible.username, ")") as mainResponsibleName, 
groupID.groupName as groupName, phaseID.phaseName as phaseName, parentTasks.taskName as parentTaskName
FROM Tasks
LEFT JOIN Users as mainResponsible on mainResponsible.userID = Tasks.mainResponsible
LEFT JOIN Groups as groupID on groupID.groupID = Tasks.groupID
LEFT JOIN Phases as phaseID on phaseID.phaseID = Tasks.phaseID
LEFT JOIN Tasks as parentTasks on parentTasks.taskID = Tasks.parentTask WHERE 1';
        $params = array();
        if (!is_null($hasSubtask)) {
            $query .= " AND Tasks.hasSubtask = :hasSubtask";
            $params[':hasSubtask'] = $hasSubtask;
        }
        if (!is_null($projectName)) {
            $query .= " AND Tasks.projectName = :projectName";
            $params[':projectName'] = $projectName;
        }
        if (!is_null($phaseID)) {
            $query .= " AND Tasks.phaseID = :phaseID";
            $params[':phaseID'] = $phaseID;
        }
        if (!is_null($groupID)) {
            $query .= " AND Tasks.groupID = :groupID";
            $params[':groupID'] = $groupID;
        }
        if (!is_null($status)) {
            $query .= " AND Tasks.status = :status";
            $params[':status'] = $status;
        }
        if (!is_null($mainResponsible)) {
            $query .= " AND Tasks.mainResponsible = :mainResponsible";
            $params[':mainResponsible'] = $mainResponsible;
        }
        if (!is_null($parentTask)) {
            $query .= " AND Tasks.parentTask = :parentTask";
            $params[':parentTask'] = $parentTask;
        }
        if (!is_null($orderBy)) {
            $query .= " ORDER BY ".$orderBy;
        }
        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            if( $tasks = $stmt->fetchAll(PDO::FETCH_CLASS, "Task")) {
                return $tasks;
            }
            else {
                return $tasks;
            }
        } catch (Exception $e) {
            $this->NotifyUser("Feil ved henting av oppgaver");
            return $tasks;
        }
    }

    // GET TASKS OF USER
    public function getTasksOfUser($userId) : array {
        $tasks = array();
        $query = 'SELECT Tasks.*, CONCAT(mainResponsible.firstName, " ", mainResponsible.lastName, " (", mainResponsible.username, ")") as mainResponsibleName, parentTasks.taskName as parentTaskName FROM Tasks
LEFT JOIN Users as mainResponsible on mainResponsible.userID = Tasks.mainResponsible
LEFT JOIN Tasks as parentTasks on parentTasks.taskID = Tasks.parentTask
LEFT JOIN UsersAndGroups as groupID on groupID.groupID = Tasks.groupID 
LEFT JOIN Projects on Tasks.projectName = Projects.projectName
WHERE groupID.userID = :userID AND Tasks.hasSubtask >= 0 AND Tasks.status < 3 AND Projects.status = 1;';
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam('userID', $userId, PDO::PARAM_INT);
            $stmt->execute();
            if( $tasks = $stmt->fetchAll(PDO::FETCH_CLASS, "Task")) {
                return $tasks;
            }
            else {
                return $tasks;
            }
        } catch (Exception $e) {
            $this->NotifyUser("Feil ved henting av dine oppgaver");
            return $tasks;
        }
    }


    // GET TASK
    public function getTask(?int $taskId)
    {
        $query = 'SELECT Tasks.*, CONCAT(mainResponsible.firstName, " ", mainResponsible.lastName, " (", mainResponsible.username, ")") as mainResponsibleName, 
groupID.groupName as groupName, phaseID.phaseName as phaseName, parentTasks.taskName as parentTaskName
FROM Tasks
LEFT JOIN Users as mainResponsible on mainResponsible.userID = Tasks.mainResponsible
LEFT JOIN Groups as groupID on groupID.groupID = Tasks.groupID
LEFT JOIN Phases as phaseID on phaseID.phaseID = Tasks.phaseID
LEFT JOIN Tasks as parentTasks on parentTasks.taskID = Tasks.parentTask WHERE Tasks.taskID = :taskID';
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':taskID', $taskId, PDO::PARAM_INT);
            $stmt->execute();
            if($task = $stmt->fetchObject("Task")) {
                return $task;
            }
            else {
                return null;
            }
        } catch (Exception $e) {
            $this->NotifyUser("En feil oppstod ved henting av oppgave");
            return null;
        }
    }


    public function addMainTask($projectName): bool //returns boolean value
    {
        $taskName = $this->request->request->get('taskName');
        $phaseId = $this->request->request->get('phaseID', null);
        $groupId = $this->request->request->get('groupID', null);
        try {
            $stmt = $this->db->prepare("INSERT INTO `Tasks` (taskName, projectName, phaseID, groupID, hasSubtask)
              VALUES (:taskName, :projectName, :phaseID, :groupID, 1);");
            $stmt->bindParam(':taskName', $taskName, PDO::PARAM_STR, 100);
            $stmt->bindParam(':projectName', $projectName, PDO::PARAM_STR, 100);
            $stmt->bindParam(':phaseID', $phaseId, PDO::PARAM_INT, 100);
            $stmt->bindParam(':groupID', $groupId, PDO::PARAM_INT, 100);
            if ($stmt->execute()) {
                $taskId = $this->db->lastInsertId();
                $stmt->closeCursor();
                $this->addToProgressTable($projectName);
                if ($this->addDependencies($taskId)) {
                    $this->NotifyUser("En oppgave ble lagt til med avhengigheter");
                    return true;
                } else {
                    $this->NotifyUser("En oppgave ble lagt til");
                    return true;
                }
            } else {
                $this->NotifyUser("Oppgave ble ikke lagt til");
                return false;
            }
        } catch (PDOException $e) {
            $this->NotifyUser("Feil ved opprettelse av ny oppgave");
            return false;
        }
    }

    public function editTask(Task $task): bool //returns boolean value
    {
        $taskId = $task->getTaskID();
        $phaseId = $this->request->request->get('phaseID', $task->getPhaseID());
        $mainResponsible = $this->request->request->get('mainResponsible', $task->getMainResponsible());
        try {
            $stmt = $this->db->prepare("UPDATE Tasks SET phaseID = :phaseID, mainResponsible = :mainResponsible WHERE taskID = :taskID;");
            $stmt->bindParam(':taskID', $taskId, PDO::PARAM_INT, 100);
            $stmt->bindParam(':mainResponsible', $mainResponsible, PDO::PARAM_INT, 100);
            $stmt->bindParam(':phaseID', $phaseId, PDO::PARAM_INT, 100);
            if ($stmt->execute()) {
                $this->NotifyUser("Oppgave ble endret");
                return true;
            } else {
                $this->NotifyUser("Oppgave ble ikke endret");
                return false;
            }
        } catch (PDOException $e) {
            $this->NotifyUser("Feil ved endring av oppgave");
            return false;
        }
    }


    public function addSubTask($projectName, $parentTask, $groupId): bool //returns boolean value
    {
        $taskName = $this->request->request->get('taskName');
        $estimate = $this->request->request->getInt('estimatedTime', 0);
        try {
            $stmt = $this->db->prepare("INSERT INTO Tasks (taskName, projectName, parentTask, groupID, estimatedTime, hasSubtask)
              VALUES (:taskName, :projectName, :parentTask, :groupID, :estimate, 0);
              UPDATE Tasks SET estimatedTime = (SELECT SUM(estimatedTime) total FROM Tasks WHERE parentTask = :parentTask) WHERE taskID = :parentTask");
            $stmt->bindParam(':taskName', $taskName, PDO::PARAM_STR, 100);
            $stmt->bindParam(':projectName', $projectName, PDO::PARAM_STR, 100);
            $stmt->bindParam(':groupID', $groupId, PDO::PARAM_INT, 100);
            $stmt->bindParam(':estimate', $estimate, PDO::PARAM_INT, 100);
            $stmt->bindParam(':parentTask', $parentTask, PDO::PARAM_INT, 100);
            if ($stmt->execute()) {
                $taskId = $this->db->lastInsertId();
                $stmt->closeCursor();
                $this->addToProgressTable($projectName);
                if ($this->addDependencies($taskId)) {
                    $this->NotifyUser("En deloppgave ble lagt til med avhengigheter");
                    return true;
                } else {
                    $this->NotifyUser("En deloppgave ble lagt til");
                    return true;
                }
            } else {
                $this->NotifyUser("Deloppgave ble ikke opprettet");
                return false;
            }
        } catch (PDOException $e) {
            $this->NotifyUser("Feil ved opprettelse av ny deloppgave");
            return false;
        }
    }


    public function addDependencies($taskId) : bool
    {
        $tasks = $this->request->request->get('dependentTasks');
        try {
            $stmt = $this->db->prepare("INSERT IGNORE INTO TaskDependencies (firstTask, secondTask) VALUES (:firstTask, :taskId);");
            if (is_array($tasks)) {
                foreach ($tasks as $task) {
                    $stmt->bindParam(':firstTask', $task, PDO::PARAM_INT);
                    $stmt->bindParam(':taskId', $taskId, PDO::PARAM_INT);
                    $stmt->execute();
                    $stmt->closeCursor();
                }
                $this->notifyUser("Avhengigheter ble lagt til");
                return true;
            } else {
                $this->notifyUser("Fikk ikke legge til avhengigheter");
                return false;
            }
        } catch (Exception $e) {
            $this->notifyUser("Feil ved opprettelse av avhengigheter");
            return false;
        }
    }


    public function removeDependencies($taskId) : bool
    {
        $tasks = $this->request->request->get('dependentTasks');
        try {
            $stmt = $this->db->prepare("DELETE FROM TaskDependencies WHERE firstTask = :firstTask and secondTask = :taskId;");
            if (is_array($tasks)) {
                foreach ($tasks as $task) {
                    $stmt->bindParam(':firstTask', $task, PDO::PARAM_INT);
                    $stmt->bindParam(':taskId', $taskId, PDO::PARAM_INT);
                    $stmt->execute();
                }
                $this->notifyUser("Avhengigheter ble fjernet");
                return true;
            } else {
                $this->notifyUser("Fikk ikke fjerne avhengigheter");
                return false;
            }
        } catch (Exception $e) {
            $this->notifyUser("Feil ved fjerning av avhengigheter");
            return false;
        }
    }




    // GET FIRST TASKS
    public function getTasksItIsDependentOn($taskId) : array {
        $tasksItIsDependentOn = array();
        try {
            $stmt = $this->db->prepare('SELECT TaskDependencies.*, Tasks.*, CONCAT(mainResponsible.firstName, " ", mainResponsible.lastName, " (", mainResponsible.username, ")") as mainResponsibleName, 
groupID.groupName as groupName, phaseID.phaseName as phaseName, parentTasks.taskName as parentTaskName
FROM TaskDependencies
LEFT JOIN Tasks on TaskDependencies.firstTask = Tasks.taskID
LEFT JOIN Users as mainResponsible on mainResponsible.userID = Tasks.mainResponsible
LEFT JOIN Groups as groupID on groupID.groupID = Tasks.groupID
LEFT JOIN Phases as phaseID on phaseID.phaseID = Tasks.phaseID
LEFT JOIN Tasks as parentTasks on parentTasks.taskID = Tasks.parentTask WHERE TaskDependencies.secondTask = :taskID ORDER BY Tasks.taskName;');
            $stmt->bindParam(':taskID', $taskId, PDO::PARAM_INT);
            $stmt->execute();
            if($tasksItIsDependentOn = $stmt->fetchAll(PDO::FETCH_CLASS, "Task")) {
                return $tasksItIsDependentOn;
            }
            else {
                return $tasksItIsDependentOn;
            }
        } catch (Exception $e) {
            $this->NotifyUser("En feil oppstod ved henting av avhengige oppgaver");
            return $tasksItIsDependentOn;
        }
    }



    // GET SECOND TASKS
    public function getDependentTasks($taskId) : array {
        $dependentTasks = array();
        try {
            $stmt = $this->db->prepare('SELECT TaskDependencies.*, Tasks.*, CONCAT(mainResponsible.firstName, " ", mainResponsible.lastName, " (", mainResponsible.username, ")") as mainResponsibleName, 
groupID.groupName as groupName, phaseID.phaseName as phaseName, parentTasks.taskName as parentTaskName
FROM TaskDependencies
LEFT JOIN Tasks on TaskDependencies.secondTask = Tasks.taskID
LEFT JOIN Users as mainResponsible on mainResponsible.userID = Tasks.mainResponsible
LEFT JOIN Groups as groupID on groupID.groupID = Tasks.groupID
LEFT JOIN Phases as phaseID on phaseID.phaseID = Tasks.phaseID
LEFT JOIN Tasks as parentTasks on parentTasks.taskID = Tasks.parentTask WHERE TaskDependencies.firstTask = :taskID ORDER BY Tasks.taskName;');
            $stmt->bindParam(':taskID', $taskId, PDO::PARAM_INT);
            $stmt->execute();
            if($dependentTasks = $stmt->fetchAll(PDO::FETCH_CLASS, "Task")) {
                return $dependentTasks;
            }
            else {
                return $dependentTasks;
            }
        } catch (Exception $e) {
            $this->NotifyUser("En feil oppstod ved henting av avhengige oppgaver");
            return $dependentTasks;
        }
    }


    // GET SECOND TASKS
    public function getNonDependentTasks($task) : array {
        $projectName = $task->getProjectName();
        $taskId = $task->getTaskID();
        $dependentTasks = array();
        try {
            $stmt = $this->db->prepare('SELECT * FROM Tasks WHERE taskID 
                              NOT IN(SELECT firstTask FROM TaskDependencies WHERE secondTask = :taskID) 
                      AND taskID NOT IN(SELECT secondTask FROM TaskDependencies WHERE firstTask = :taskID) 
                      AND taskID != :taskID AND projectName = :projectName;');
            $stmt->bindParam(':taskID', $taskId, PDO::PARAM_INT);
            $stmt->bindPAram(':projectName', $projectName, PDO::PARAM_STR);
            $stmt->execute();
            if($dependentTasks = $stmt->fetchAll(PDO::FETCH_CLASS, "Task")) {
                return $dependentTasks;
            }
            else {
                return $dependentTasks;
            }
        } catch (Exception $e) {
            $this->NotifyUser("En feil oppstod ved henting av ikke-avhengige oppgaver");
            return $dependentTasks;
        }
    }



    public function addTasksToPhase($phaseId) : bool
    {
        $tasks = $this->request->request->get('tasks');
        try {
            $stmt = $this->db->prepare("UPDATE Tasks SET Tasks.phaseID = :phaseID WHERE Tasks.taskID = :taskID;");
            if (is_array($tasks)) {
                foreach ($tasks as $task) {
                    $stmt->bindParam(':phaseID', $phaseId, PDO::PARAM_INT);
                    $stmt->bindParam(':taskID', $task, PDO::PARAM_INT);
                    $stmt->execute();
                }
                $this->notifyUser("Oppgave ble lagt til på fasen");
                return true;
            } else {
                $this->notifyUser("Fikk ikke legge til oppgaver på fasen");
                return false;
            }
        } catch (Exception $e) {
            $this->notifyUser("En feil oppstod, fikk ikke legge til oppgaver på fasen");
            return false;
        }
    }


    public function removeTasksFromPhase($phaseId) : bool
    {
        $tasks = $this->request->request->get('tasks');
        try {
            $stmt = $this->db->prepare("UPDATE Tasks SET Tasks.phaseID = null WHERE Tasks.taskID = :taskID AND Tasks.phaseID = :phaseID;");
            if (is_array($tasks)) {
                foreach ($tasks as $task) {
                    $stmt->bindParam(':phaseID', $phaseId, PDO::PARAM_INT);
                    $stmt->bindParam(':taskID', $task, PDO::PARAM_INT);
                    $stmt->execute();
                }
                $this->notifyUser("Oppgave fjernet fra fasen");
                return true;
            } else {
                $this->notifyUser("Fikk ikke fjerne oppgaver fra fasen");
                return false;
            }
        } catch (Exception $e) {
            $this->notifyUser("En feil oppstod, fikk ikke fjerne oppgaver fra fasen");
            return false;
        }
    }

    public function editStatus($taskId) : bool
    {
        $status = $this->request->request->get('status');
        try {
            $stmt = $this->db->prepare("UPDATE Tasks SET status = :status WHERE taskID = :taskID;
                UPDATE Tasks SET status = :status WHERE parentTask = :taskID");
            $stmt->bindParam(':taskID', $taskId, PDO::PARAM_INT);
            $stmt->bindParam(':status', $status, PDO::PARAM_INT);
            if ($stmt->execute()) {
                $this->notifyUser("Status på oppgaven ble endret");
                $stmt->closeCursor();
                $task = $this->getTask($taskId);
                $this->addToProgressTable($task->getProjectName());
                return true;
            } else {
                $this->notifyUser("Fikk ikke endre status til oppgaver");
                return false;
            }
        } catch (Exception $e) {
            $this->notifyUser("Feil ved endring av status på oppgaven");
            return false;
        }
    }

    public function addToProgressTable($projectName) : bool {
        try {
            $stmt = $this->db->prepare("INSERT INTO `ProgressTable`(`projectName`, `sumEstimate`, `sumEstimateDone`, `sumTimeSpent`, `registerDate`)
SELECT Projects.projectName, CASE WHEN Tasks.estimatedTime IS NULL THEN 0 ELSE SUM(Tasks.estimatedTime) END AS sumEstimate, CASE WHEN Tasks.estimatedTime IS NULL THEN 0 ELSE SUM(CASE WHEN Tasks.status = 3 THEN Tasks.estimatedTime ELSE 0 END) END AS sumEstimateDone, CASE WHEN Tasks.timeSpent IS NULL THEN 0 ELSE SUM(Tasks.timeSpent) END AS sumTimeSpent, NOW()
FROM Projects
LEFT JOIN Tasks on Projects.projectName = Tasks.projectName 
WHERE (Tasks.hasSubtask = 0 OR Tasks.hasSubtask IS NULL) AND (Projects.status > 0 AND Projects.isAcceptedByAdmin = 1) AND Projects.projectName = :projectName GROUP BY Projects.projectName");
            $stmt->bindParam(':projectName', $projectName, PDO::PARAM_STR);
            if ($stmt->execute()) {
                return true;
            } else {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    public function changeGroup($taskId): bool //returns boolean value
    {
        $groupId = $this->request->request->get('group');
        try {
            $stmt = $this->db->prepare("UPDATE Tasks SET groupID = :groupID WHERE taskID = :taskID;
                UPDATE Tasks SET groupID = :groupID WHERE parentTask = :taskID;");
            $stmt->bindParam(':taskID', $taskId, PDO::PARAM_INT, 100);
            $stmt->bindParam(':groupID', $groupId, PDO::PARAM_INT, 100);
            if ($stmt->execute()) {
                $this->NotifyUser("Gruppe ble endret");
                return true;
            } else {
                $this->NotifyUser("Gruppe ble ikke endret");
                return false;
            }
        } catch (PDOException $e) {
            $this->NotifyUser("Feil ved endring av gruppe ble");
            return false;
        }
    }



    public function reEstimate($taskId, $parentTaskId = null) : bool
    {
        $estimatedTime = $this->request->request->get('estimatedTime');
        try {
            $stmt = $this->db->prepare("UPDATE Tasks SET estimatedTime = :estimatedTime WHERE taskID = :taskID;
                UPDATE Tasks SET estimatedTime = (SELECT SUM(estimatedTime) total FROM Tasks WHERE parentTask = :parentTaskID) WHERE taskID = :parentTaskID");
            $stmt->bindParam(':taskID', $taskId, PDO::PARAM_INT);
            $stmt->bindParam(':estimatedTime', $estimatedTime, PDO::PARAM_INT);
            $stmt->bindParam(':parentTaskID', $parentTaskId, PDO::PARAM_INT);
            if ($stmt->execute()) {
                $this->notifyUser("Estimert tid på oppgaven ble endret");
                $stmt->closeCursor();
                $task = $this->getTask($taskId);
                $this->addToProgressTable($task->getProjectName());
                return true;
            } else {
                $this->notifyUser("Fikk ikke endre estimert tid til oppgaver");
                return false;
            }
        } catch (Exception $e) {
            $this->notifyUser("Feil ved re-estimering av oppgaven");
            return false;
        }
    }


    public function deleteTask($taskId, $parentTaskId = null) : bool
    {
        $task = $this->getTask($taskId);
        $projectName = $task->getProjectName();
        if (!is_null($parentTaskId)) {
            try {
                $stmt = $this->db->prepare("DELETE FROM Tasks Where taskID = :taskID;
                UPDATE Tasks SET estimatedTime = (SELECT SUM(estimatedTime) total FROM Tasks WHERE parentTask = :parentTaskID) WHERE taskID = :parentTaskID");
                $stmt->bindParam(':taskID', $taskId, PDO::PARAM_INT);
                $stmt->bindParam(':parentTaskID', $parentTaskId, PDO::PARAM_INT);
                if ($stmt->execute()) {
                    $this->notifyUser("Oppgave ble slettet");
                    $stmt->closeCursor();
                    $this->addToProgressTable($projectName);
                    return true;
                } else {
                    $this->notifyUser("Fikk ikke slette oppgave");
                    return false;
                }
            } catch (Exception $e) {
                $this->notifyUser("Feil ved sletting av oppgave");
                return false;
            }
        } else {
            try {
                $stmt = $this->db->prepare("DELETE FROM Tasks Where taskID = :taskID;");
                $stmt->bindParam(':taskID', $taskId, PDO::PARAM_INT);
                if ($stmt->execute()) {
                    $this->notifyUser("Oppgave ble slettet");
                    $stmt->closeCursor();
                    $this->addToProgressTable($projectName);
                    return true;
                } else {
                    $this->notifyUser("Fikk ikke slette oppgave");
                    return false;
                }
            } catch (Exception $e) {
                $this->notifyUser("Feil ved sletting av oppgave");
                return false;
            }
        }
    }

    // GET ALL TASKTYPES ------------------------------------------------------------------
    public function getCategories() : array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM TaskCategories");
            $stmt->execute();
            if( $categories = $stmt->fetchAll(PDO::FETCH_CLASS, "TaskCategory")) {
                return $categories;
            }
            else {
                return array();
            }
        } catch (Exception $e) {
            $this->NotifyUser("Feil ved henting av kategorier");
            return array();
        }
    }


}
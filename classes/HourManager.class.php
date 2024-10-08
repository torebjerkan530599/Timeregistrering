<?php


class HourManager
{
    private $dbase;
    private $request;
    private $session;

    // CONSTRUCTOR -------------------------------------------------
    public function __construct(PDO $db, \Symfony\Component\HttpFoundation\Request $request, \Symfony\Component\HttpFoundation\Session\Session $session)
    {
        $this->dbase = $db;
        $this->request = $request;
        $this->session = $session;
    }

    // FEILMELDINGER -------------------------------------------------
    private function notifyUser($strHeader, $strMessage = "")
    {
        $this->session->getFlashBag()->clear();
        $this->session->getFlashBag()->add('header', $strHeader);
        $this->session->getFlashBag()->add('message', $strMessage);
    }

    //PAYMENT ---------------------------
    function calculateDay($statistics): array
    {
        $array = array();
        $sum0 = strtotime('00:00');
        $sum2 = 0;
        foreach ($statistics as $statistic) {
            $sum1 = strtotime($statistic->sumTW) - $sum0;
            $sum2 = $sum2 + $sum1;
        }
        $sum3 = $sum0 + $sum2;
        $sumTime = date("H:i", $sum3);

        $timeprice = 1500;
        $a = preg_split('/[: ]/', $sumTime);
        $hour = $a[0];
        $minute = $a[1];
        $payment = $hour * 1500 + $minute * $timeprice / 60;
        array_push($array, $payment, $sumTime);
        return $array;
    }

    function calculateMonth($statistics): array //TODO sjekk om det virker riktig
    {
        $array = array();
        $sum0 = strtotime('00:00');
        $sum2 = 0;
        foreach ($statistics as $statistic) {
            $sum1 = strtotime($statistic->sumThisMonth) - $sum0;
            $sum2 = $sum2 + $sum1;
        }
        $sum3 = $sum0 + $sum2;
        $sumTime = date("H:i", $sum3);

        $timeprice = 1500;
        $a = preg_split('/[: ]/', $sumTime);
        $hour = $a[0];
        $minute = $a[1];
        $payment = $hour * 1500 + $minute * $timeprice / 60;
        array_push($array, $payment, $sumTime);
        return $array;
    }


    // GET ALL Hours With All Users --------------------------------------------------------------------------------
    public function getAllHours()
    {
        $hoursAll = array();
        $query = 'SELECT SEC_TO_TIME(timeWorked) as tw, Hours.*, hourTasks.*, CONCAT(workers.firstName, " ", workers.lastName) as whoWorkedName, 
                    hourTasks.taskName as taskName, hourPhases.phaseName as phaseName
                    FROM Hours
                    LEFT JOIN Users as workers on workers.userID = Hours.whoWorked
                    LEFT JOIN Tasks as hourTasks on hourTasks.taskID = Hours.taskID
                    LEFT JOIN Phases as hourPhases on hourPhases.phaseID = Hours.phaseID WHERE Hours.stampingStatus = 1
                    ORDER BY Hours.startTime DESC';
        try {
            $stmt = $this->dbase->prepare($query);
            $stmt->execute();
            if ($hoursAll = $stmt->fetchAll(PDO::FETCH_CLASS, "Hour")) {
                return $hoursAll;
            } else {
                return $hoursAll;
            }
        } catch (Exception $e) {
            $this->NotifyUser("En feil oppstod ved henting av timer!");
            print $e->getMessage() . PHP_EOL;
            return $hoursAll;
        }
    }

    // REGISTER TIME FOR USER ---------------------------------------------------------------------------
    public function registerTimeForUser($userID, ?Task $task): bool
    {
        if (!is_null($task)) {
            $taskID = $task->getTaskID();
            $phaseID = $task->getPhaseID();
        } else {
            $taskID = null;
            $phaseID = null;
        }

        $location = $this->request->request->get('Lokasjon');
        $phaseID = NULL;
        $absenceType = NULL;
        $overtimeType = NULL;
        $comment = NULL;
        $commentBoss = NULL;
        $isChanged = 0;
        $stampingStatus = 0;
        $taskType = $this->request->request->get('Kategori');
        if ($taskType != "Prosjekt") {
            $taskID = null;
        }

        try {
            $stmt = $this->dbase->prepare("INSERT INTO Hours (`taskID`, `whoWorked`, `startTime`, 
                   `endTime`, `timeWorked`, `activated`, `location`, `phaseID`, `absenceType`, `overtimeType`, 
                   `comment`, `commentBoss`, `isChanged`, `stampingStatus`, `taskType`)
                   VALUES (:taskID, :userID, NOW(), 0, 0, 1, 
                           :location, :phaseID, :absenceType, :overtimeType, :comment, 
                           :commentBoss, 0, 0, :taskType)");

            $stmt->bindParam(':taskID', $taskID, PDO::PARAM_INT);
            $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
            $stmt->bindParam(':location', $location, PDO::PARAM_STR);
            $stmt->bindParam(':phaseID', $phaseID, PDO::PARAM_STR);
            $stmt->bindParam(':absenceType', $absenceType, PDO::PARAM_STR);
            $stmt->bindParam(':overtimeType', $overtimeType, PDO::PARAM_STR);
            $stmt->bindParam(':comment', $comment, PDO::PARAM_STR);
            $stmt->bindParam(':commentBoss', $commentBoss, PDO::PARAM_STR);
            //$stmt->bindParam(':isChanged', $isChanged, PDO::PARAM_STR);
            //$stmt->bindParam(':stampingStatus', $stampingStatus, PDO::PARAM_STR);
            $stmt->bindParam(':taskType', $taskType, PDO::PARAM_STR);

            $stmt->execute();
            if ($stmt->rowCount() == 1) {
                return true;
            } else {
                $this->notifyUser("Registrering av time ble ikke startet");
                return false;
            }
        } catch (Exception $e) {
            $this->NotifyUser("En feil oppstod ved registrering av ny time");
            return false;
        }
    }


    // STOP TIME FOR USER ---------------------------------------------------------------------------
    public function stopTimeForUser(?Hour $hour, ?Task $task): bool
    {
        if (is_null($hour)) {
            return false;
        }
        $hourID = $hour->getHourID();
        try {
            $stmt = $this->dbase->prepare("UPDATE Hours SET endTime = NOW(), 
                 stampingStatus = 1 WHERE hourID = :hourID");
            $stmt->bindParam(':hourID', $hourID, PDO::PARAM_INT);
            $stmt->execute();
            $stmt->closeCursor();
            if ($stmt->rowCount() == 1) {
                if (!is_null($task)) {
                    $this->updateTimeWorkedOnTask($task);
                }
                return true;
            } else {
                $this->notifyUser("Fikk ikke stemple ut time");
                return false;
            }
        } catch (Exception $e) {
            $this->NotifyUser("En feil oppstod ved stempling ut av time");
            return false;
        }
    }



    public function activeTimeregForUser($userID)
    {
        try {
            $stmt = $this->dbase->prepare("SELECT hourID FROM Hours WHERE whoWorked = :userID AND stampingStatus = 0");
            $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
            $stmt->execute();
            if ($hourID = $stmt->fetch()) {
                return $hourID;
            } else {
                return array();
            }
        } catch (Exception $e) {
            $this->NotifyUser("En feil oppstod på ved sjekk av aktiv time");
            return array();
        }
    }

    public function totalTimeWorked($projectName)
    {
        try {
            $stmt = $this->dbase->prepare("SELECT DISTINCT Users.*, CONCAT(Users.firstName, ' ', Users.lastName) as whoWorkedName, CASE WHEN hrs.sumTW is null then '00:00' ELSE hrs.sumTW END as sumTW FROM Users 
LEFT JOIN UsersAndGroups ON Users.userID = UsersAndGroups.userID 
LEFT JOIN Groups ON UsersAndGroups.groupID = Groups.groupID 
LEFT JOIN 
(SELECT TIME_FORMAT(SEC_TO_TIME(SUM(timeWorked)), '%H:%i') as sumTW, hourTasks.taskID as taskid, Hours.whoWorked as userid FROM Hours
LEFT JOIN Tasks as hourTasks on hourTasks.taskID = Hours.taskID WHERE hourTasks.projectName = :projectName GROUP BY Hours.whoWorked) as hrs on hrs.userid = Users.userID 
WHERE Groups.projectName = :projectName ORDER BY whoWorkedName");
            $stmt->bindParam(':projectName', $projectName, PDO::PARAM_STR);
            $stmt->execute();
            if ($totalTimeWorked = $stmt->fetchAll()) {
                return $totalTimeWorked;
            } else {
                return array();
            }
        } catch (Exception $e) {
            $this->NotifyUser("En feil oppstod ved henting av total tidsbruk");
            return array();
        }
    }
    // TOTAL TIME USERS



    private function updateTimeWorkedOnTask(?Task $task)
    {
        if (is_null($task)) {
            return false;
        } else {
            $taskID = $task->getTaskID();
            $parentTaskID = $task->getParentTask();
            try {
                $stmt = $this->dbase->prepare("UPDATE Tasks SET timeSpent = (SELECT SUM(TIME_TO_SEC(timeWorked)/3600) total FROM Hours WHERE taskID = :taskID) WHERE taskID = :taskID;
                                 UPDATE Tasks SET timeSpent = (SELECT SUM(timeSpent) total FROM Tasks WHERE parentTask = :parentTaskID) WHERE taskID = :parentTaskID;");
                $stmt->bindParam(':taskID', $taskID, PDO::PARAM_INT);
                $stmt->bindParam(':parentTaskID', $parentTaskID, PDO::PARAM_INT);
                //   $stmt->closeCursor();
                if ($stmt->execute()) {
                    return true;
                } else {
                    $this->notifyUser("Tid brukt på oppgave ble ikke oppdatert");
                    return false;
                }
            } catch (Exception $e) {
                $this->NotifyUser("En feil oppstod ved oppdatering av tidsbruk på oppgave");
                return false;
            }

        }


    }

    // GET HOUR ---------------------------------------------------------------------------------
    public function getHour($hourID)
    {
        $hour = null;
        try {
            $stmt = $this->dbase->prepare("SELECT * FROM Hours Where hourID = :hourID");
            $stmt->bindParam(':hourID', $hourID, PDO::PARAM_INT);
            $stmt->execute();
            if ($hour = $stmt->fetchObject("Hour")) {
                return $hour;
            } else {
                return null;
            }
        } catch (Exception $e) {
            $this->NotifyUser("En feil oppstod, kunne ikke hente økt");
            return null;
        }
    }

    //EDIT COMMENT USER --------------------------------------------------------------------------------
    public function editComment($hourID): bool
    {
        $hourID = $this->request->request->get('hourID');
        $comment = $this->request->request->get('comment');
        try {
            $stmt = $this->dbase->prepare("UPDATE Hours SET comment = :comment WHERE hourID = :hourID;");
            $stmt->bindParam(':hourID', $hourID, PDO::PARAM_INT);
            $stmt->bindParam(':comment', $comment);
            if ($stmt->execute()) {
                $stmt->closeCursor();
                $this->notifyUser('Kommentar endret');
                return true;
            } else {
                $this->notifyUser('Kommentar ble ikke endret');
                return false;
            }
        } catch (Exception $e) {
            $this->notifyUser("En feil oppstod, kommentar ble ikke endret");
            return false;
        }
    }

    //EDIT COMMENT BOSS--------------------------------------------------------------------------------
    public function editCommentBoss($hourID): bool
    {
        $hourID = $this->request->request->get('hourID');
        $commentBoss = $this->request->request->get('commentBoss');
        try {
            $stmt = $this->dbase->prepare("UPDATE Hours SET commentBoss = :commentBoss WHERE hourID = :hourID;");
            $stmt->bindParam(':hourID', $hourID, PDO::PARAM_INT);
            $stmt->bindParam(':commentBoss', $commentBoss);
            if ($stmt->execute()) {
                $stmt->closeCursor();
                $this->notifyUser('Leder endret kommentar');
                return true;
            } else {
                $this->notifyUser('Kommentar ble ikke endret');
                return false;
            }
        } catch (Exception $e) {
            $this->notifyUser("En feil oppstod, kommentar ble ikke endret");
            return false;
        }
    }

    public function checkIfActiveTimereg($userID): array
    {
        try {
            $stmt = $this->dbase->prepare("SELECT stampingStatus, hourID FROM Hours WHERE whoWorked = :userID ;");
            $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);
            $stmt->execute();
            if ($timeregCheck = $stmt->fetchAll(PDO::FETCH_CLASS, "Hour")) {
                return $timeregCheck;
            } else {
                return array();
            }
        } catch (Exception $e) {
            return array();
        }
    }

    //Edit the hour, deactivate old
    public function changeTimeForUser($hourID, $startTime, $endTime, $activated, ?Task $task): bool
    {
        $isChanged = 1;
        try {
            $stmt = $this->dbase->prepare("UPDATE Hours SET isChanged = :isChanged, startTime = :startTime, endTime = :endTime WHERE hourID = :hourID;");
            $stmt->bindParam(':hourID', $hourID, PDO::PARAM_INT);
            $stmt->bindParam(':startTime', $startTime);
            $stmt->bindParam(':endTime', $endTime);
            $stmt->bindParam(':isChanged', $isChanged);

            $stmt->execute();
            if ($stmt->rowCount() == 1) {
                //$stmt->closeCursor();
                if (!is_null($task)) {
                    //$hour = $this->getHour($hourID);
                    //$timeStr = $hour->getTimeWorked();
                    $this->updateTimeWorkedOnTask($task);
                }
                return true;
            } else {
                return false;
            }
        } catch (Exception $e) {
            $this->notifyUser("En feil oppstod ved endring av time");
            return false;
        }
    }

    //VERIFY HOUR
    public function verifyHour($hourID): bool
    {
        $activated = 1;
        try {
            $stmt = $this->dbase->prepare("UPDATE Hours SET activated = :activated WHERE hourID = :hourID;");
            $stmt->bindParam(':hourID', $hourID, PDO::PARAM_INT);
            $stmt->bindParam(':activated', $activated);
            $stmt->execute();
            if ($stmt->execute()) {
                $stmt->closeCursor();
                $this->notifyUser('Time er verifisert av administrator');
                return true;
            } else {
                $this->notifyUser('Time ble ikke verifisert');
                return false;
            }
        } catch (Exception $e) {
            $this->notifyUser("En feil oppstod ved verifisering av time");
            return false;
        }
    }


    public function duplicateToLog(Hour $hour): bool
    {
        $hourID = $hour->getHourID();
        $taskID = $hour->getTaskID();
        $userID = $hour->getWhoworked();
        $startTime = $hour->getStartTime();
        $endTime = $hour->getEndTime();
        $timeWorked = $hour->getTimeWorked();
        $activated = $hour->isActivated();
        $location = $hour->getLocation();
        $phaseID = $hour->getPhaseID();
        $absenceType = $hour->getAbsenceType();
        $overtimeType = $hour->getOvertimeType();
        $comment = $hour->getComment();
        $commentBoss = $hour->getCommentBoss();
        $isChanged = 1;
        $stampingStatus = $hour->isStampingStatus();
        $taskType = $hour->getTaskType();

        try {
            $stmt = $this->dbase->prepare("INSERT INTO HoursLogs (`timeChanged`, `hourID`, `taskID`, `whoWorked`, `startTime`, 
                   `endTime`, `timeWorked`, `activated`, `location`, `phaseID`, `absenceType`, `overtimeType`, 
                   `comment`, `commentBoss`, `isChanged`, `stampingStatus`, `taskType`)
                   VALUES (NOW(), :hourID, :taskID, :userID, :startTime, :endTime, :timeWorked, :activated, 
                           :location, :phaseID, :absenceType, :overtimeType, :comment, 
                           :commentBoss, :isChanged, :stampingStatus, :taskType)");

            $stmt->bindParam(':hourID', $hourID, PDO::PARAM_STR);
            $stmt->bindParam(':taskID', $taskID, PDO::PARAM_STR);
            $stmt->bindParam(':userID', $userID, PDO::PARAM_STR);
            $stmt->bindParam(':startTime', $startTime, PDO::PARAM_STR);
            $stmt->bindParam(':endTime', $endTime, PDO::PARAM_STR);
            $stmt->bindParam(':timeWorked', $timeWorked, PDO::PARAM_STR);
            $stmt->bindParam(':activated', $activated, PDO::PARAM_STR);
            $stmt->bindParam(':location', $location, PDO::PARAM_STR);
            $stmt->bindParam(':phaseID', $phaseID, PDO::PARAM_STR);
            $stmt->bindParam(':absenceType', $absenceType, PDO::PARAM_STR);
            $stmt->bindParam(':overtimeType', $overtimeType, PDO::PARAM_STR);
            $stmt->bindParam(':comment', $comment, PDO::PARAM_STR);
            $stmt->bindParam(':commentBoss', $commentBoss, PDO::PARAM_STR);
            $stmt->bindParam(':isChanged', $isChanged, PDO::PARAM_STR);
            $stmt->bindParam(':stampingStatus', $stampingStatus, PDO::PARAM_STR);
            $stmt->bindParam(':taskType', $taskType, PDO::PARAM_STR);

            $stmt->execute();
            if ($stmt->rowCount() == 1) {
                return true;
            } else {
                return false;
            }
        } catch (Exception $e) {
            $this->NotifyUser("En feil oppstod ved lagring på deaktiverte timer");
            return false;
        }
    }

    //Deaktivere en timereg.
    public function deleteTimeForUser($hourID, ?Task $task): bool
    {
        try {
            $stmt = $this->dbase->prepare("DELETE FROM Hours WHERE hourID = :hourID;
                                        ");
            $stmt->bindParam(':hourID', $hourID, PDO::PARAM_INT);
            if ($stmt->execute()) {
                $stmt->closeCursor();
                if (!is_null($task)) {
                    //$hour = $this->getHour($hourID);
                    //$timeStr = $hour->getTimeWorked();
                    $this->updateTimeWorkedOnTask($task);
                }
                return true;
            } else {
                $this->notifyUser('Timeregistrering ikke deaktivert!',);
                return false;
            }
        } catch (Exception $e) {
            $this->notifyUser("En feil oppstod ved deaktivering av timer");
            return false;
        }
    }

    public function getDeletedHours()
    {
        $deletedHours = array();
        $query = 'SELECT SEC_TO_TIME(timeWorked) as tw, HoursLogs.*, CONCAT(workers.firstName, " ", workers.lastName) as whoWorkedName
                    FROM HoursLogs
                    LEFT JOIN Users as workers on workers.userID = HoursLogs.whoWorked      
                    ORDER BY HoursLogs.whoWorked DESC';
        try {
            $stmt = $this->dbase->prepare($query);
            $stmt->execute();
            if ($deletedHours = $stmt->fetchAll(PDO::FETCH_CLASS, "Hour")) {
                return $deletedHours;
            } else {
                return $deletedHours;
            }
        } catch (Exception $e) {
            return $deletedHours;
        }
    }

    // GET ALL TASKS -------------------------------------------------------------------------------------
    public function getHours($taskId = null, $whoWorked = null, $phaseId = null, $startTime = null, $endTime = null,
                             $timeWorked = null, $activated = null, $location = null, $absenceType = null,
                             $overtimeType = null, $isChanged = null, $stampingStatus = null, $taskType = null,
                             $orderBy = null, $offset = null, $limit = null, $projectName = null): array
    {
        $hours = array();
        $query = 'SELECT SEC_TO_TIME(timeWorked) as tw, Hours.*, hourTasks.*, CONCAT(workers.firstName, " ", workers.lastName) as whoWorkedName, 
                    hourTasks.taskName as taskName, hourPhases.phaseName as phaseName
                    FROM Hours
                    LEFT JOIN Users as workers on workers.userID = Hours.whoWorked
                    LEFT JOIN Tasks as hourTasks on hourTasks.taskID = Hours.taskID
                    LEFT JOIN Phases as hourPhases on hourPhases.phaseID = Hours.phaseID WHERE workers.userID = Hours.whoWorked';
        $params = array();
        if (!is_null($taskId)) {
            $query .= " AND Hours.taskID = :taskID";
            $params[':taskID'] = $taskId;
        }
        if (!is_null($whoWorked)) {
            $query .= " AND Hours.whoWorked = :whoWorked";
            $params[':whoWorked'] = $whoWorked;
        }
        if (!is_null($phaseId)) {
            $query .= " AND Hours.phaseID = :phaseID";
            $params[':phaseID'] = $phaseId;
        }
        if (!is_null($startTime)) {
            $query .= " AND Hours.startTime > :startTime";
            $params[':startTime'] = $startTime;
        }
        if (!is_null($endTime)) {
            $query .= " AND Hours.endTime < :endTime";
            $params[':endTime'] = $endTime;
        }
        if (!is_null($timeWorked)) {
            $query .= " AND Hours.timeWorked = :timeWorked";
            $params[':timeWorked'] = $timeWorked;
        }
        if (!is_null($activated)) {
            $query .= " AND Hours.activated = :activated";
            $params[':activated'] = $activated;
        }
        if (!is_null($location)) {
            $query .= " AND Hours.location = :location";
            $params[':location'] = $location;
        }
        if (!is_null($absenceType)) {
            $query .= " AND Hours.absenceType = :absenceType";
            $params[':absenceType'] = $absenceType;
        }
        if (!is_null($overtimeType)) {
            $query .= " AND Hours.overtimeType = :overtimeType";
            $params[':overtimeType'] = $overtimeType;
        }
        if (!is_null($isChanged)) {
            $query .= " AND Hours.isChanged = :isChanged";
            $params[':isChanged'] = $isChanged;
        }
        if (!is_null($stampingStatus)) {
            $query .= " AND Hours.stampingStatus = :stampingStatus";
            $params[':stampingStatus'] = $stampingStatus;
        }
        if (!is_null($taskType)) {
            $query .= " AND Hours.activated = :taskType";
            $params[':taskType'] = $taskType;
        }
        if (!is_null($projectName)) {
            $query .= " AND hourTasks.projectName = :projectName";
            $params[':projectName'] = $projectName;
        }
        if (!is_null($orderBy)) {
            $query .= " ORDER BY " . $orderBy . " DESC";
        } else {
            $query .= " ORDER BY Hours.startTime DESC"; //hvis null så DESC på startime
        }
        if (!is_null($limit)) {
            $limit = intval($limit);
            if (!is_null($offset)) {
                $offset = intval($offset);
                $query .= " LIMIT " . $offset . ", " . $limit;
            } else {
                $query .= " LIMIT " . $limit;
            }
        }
        try {
            $stmt = $this->dbase->prepare($query);
            $stmt->execute($params);
            if ($hours = $stmt->fetchAll(PDO::FETCH_CLASS, "Hour")) {
                return $hours;
            } else {
                return $hours;
            }
        } catch (Exception $e) {
            $this->NotifyUser("En feil oppstod ved henting av timer");
            return $hours;
        }
    }

}
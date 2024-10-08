<?php

require_once "includes.php";

$reportGenerator = new ReportGenerator($db, $request, $session);

$project = $reportGenerator->getDataOnProjectForReport($request->query->getInt('projectid'));

if (!is_null($user) and !is_null($project)) {

    $userManager = new UserManager($db, $request, $session);
    $taskManager = new TaskManager($db, $request, $session);
    $hourManager = new HourManager($db, $request, $session);
    $projectManager = new ProjectManager($db, $request, $session);

    $projectName = $project->getProjectName();
    $userID = $request->query->getInt('userID');
    $users = $userManager->getAllUsers("firstName"); //alle brukere
    $customers = $userManager->getAllCustomers("firstName"); //alle kunder
    $employees = $userManager->getAllEmployees("firstName"); //alle arbeidere

    /* Sjekk om bruker er medlem i fremviste prosjekt */
    $isMember = $projectManager->checkIfMemberOfProject($projectName, $user->getUserID());

    if (!($user->isAdmin() or $user->isProjectLeader() or $isMember)) {
        header("location: reports.php");
        exit();
    }

    $tasks = $taskManager->getAllTasks(1, $projectName);
    $phases = $projectManager->getAllPhases($projectName);

    $progressData = $reportGenerator->getProgressData($projectName);


    $startDate = strtotime($project->getStartTime());
    $finishDate = strtotime($project->getFinishTime());
    $sumDays = $finishDate - $startDate;
    $sumDays = round($sumDays / (60 * 60 * 24));
    $sumEstimate = 50; //$project->sumEstimate;
    $idealHoursPerDay = $sumEstimate / $sumDays;

    if ($sumEstimate <= 0) {
        $idealTrendArray = array_fill(0, $sumDays, 0);
    }
    else {
        $idealTrendArray =  range(0, $sumEstimate, $idealHoursPerDay);
    }

    $idealXArray = array();
    $n = 1;
    for($i = 1; $i <= $sumDays; $i++){
        $idealXArray[] = 'Dag '.$i;
    }

    $actualBurn = array();
    $day = 0;
    $n = 1;

    $curDate = time();
    $curDay = intval(($curDate - $startDate)/(60*60*24));

    $prevSumEstimateDone = 0;

    foreach ($progressData as $data) {
        $date = strtotime($data['registerDate']);
        $day = intval(($date - $startDate)/(60*60*24)) + 1;
        if ($n < $day) {
            $z = $n;
            for($i = $z; $i < $day; $i++) {
                $actualBurn[] = $prevSumEstimateDone;
                $n++;
            }
            $actualBurn[] = floatval($data['sumEstimateDone']);
            $n++;
        } else if ($n == $day) {
            $actualBurn[] = floatval($data['sumEstimateDone']);
            $n++;
        } else if ($n > $day) {
            $actualBurn[$n-2] = floatval($data['sumEstimateDone']);
        }
        //$actualBurn[] = floatval($data['sumEstimateDone']);
        $prevSumEstimateDone = floatval($data['sumEstimateDone']);
    }

    if ($n < $curDay and $curDay <= $sumDays) {
        for($i = $n; $i <= $curDay; $i++) {
            $actualBurn[] = $prevSumEstimateDone;
        }
    }

    $hours = $hourManager->getAllHours();
    $groups = $projectManager->getGroups($projectName);
    $groupFromUserAndGroups = $projectManager->getGroupFromUserAndGroups($projectName); //henter gruppe basert på UsersAndGroups tabell. Joiner Group tabell og sjekker prosjektname

    $totalTimeWorked = $hourManager->totalTimeWorked($projectName);


    echo $twig->render('report_print.twig',
        array('session' => $session, 'request' => $request, 'user' => $user,

            'users' => $users,
            'customers' => $customers,
            'employees' => $employees,
            'project' => $project,
            'totalTimeWorked' => $totalTimeWorked,
            'phases' => $phases, 'tasks' => $tasks,
            'hours' => $hours,
            'groups' => $groups,
            'groupFromUserAndGroups' => $groupFromUserAndGroups,
            'hourManager' => $hourManager,
            'progressData' => $progressData,
            'idealTrendArray' => $idealTrendArray,
            'idealXArray' => $idealXArray,
            'sumEstimate' => $sumEstimate,
            'actualBurn' => $actualBurn));
} else {
    header("location: reports.php");
    exit();
}
<?php

require_once "includes.php";



if (!is_null($user) and ($user->isAdmin() or $user->isProjectLeader() or $user->isGroupleader())) {
    $projectManager = new ProjectManager($db, $request, $session);
    $userManager = new UserManager($db, $request, $session);
    $taskManager = new TaskManager($db, $request, $session);
    $hourManager = new HourManager($db, $request, $session);

    $project = $projectManager->getDataOnProjectForReport($request->query->getInt('projectid'));

    $projectName = $project->getProjectName();
    $userID = $request->query->getInt('userID');
    $users = $userManager->getAllUsers("firstName"); //alle brukere
    $customers = $userManager->getAllCustomers("firstName"); //alle kunder
    $employees = $userManager->getAllEmployees("firstName"); //alle arbeidere
    $members = $projectManager->getProjectMembers($project->getProjectName()); //alle medlemmer av dette prosjektet
    $candidates = $projectManager->getLeaderCandidates($projectName); //alle som kan bli prosjektleder

    $tasks = $taskManager->getAllTasks(hasSubtask: 1, projectName: $projectName);
    $phases = $projectManager->getAllPhases($projectName);

    $progressData = $projectManager->getProgressData($projectName);
    $progressDataJson = json_encode($progressData);

    $actualBurn = array();
    $day = 0;
    $n = 1;
    $startDate = strtotime($project->getStartTime());
    $finishDate = strtotime($project->getFinishTime());

    $prevSumEstimateDone = 0;

    foreach ($progressData as $data) {
        $date = strtotime($data['registerDate']);
        $day = intval(($date - $startDate)/(60*60*24));
        if ($n < $day) {
            $z = $n;
            for($i = $z; $i < $day; $i++) {
                $actualBurn[] = $prevSumEstimateDone;
                $n++;
            }
        } else if ($n == $day) {
            $actualBurn[] = floatval($data['sumEstimateDone']);
            $n++;
        }
        //$actualBurn[] = floatval($data['sumEstimateDone']);
        $prevSumEstimateDone = floatval($data['sumEstimateDone']);
    }

    $sumDays = strtotime($project->getFinishTime()) - strtotime($project->getStartTime());
    $sumDays = round($sumDays / (60 * 60 * 24));
    $sumEstimate = $project->sumEstimate;
    $idealHoursPerDay = $sumEstimate / $sumDays;

    $idealTrendArray =  range(0, $sumEstimate, $idealHoursPerDay);

    $idealXArray = array();
    $n = 1;
    foreach ($idealTrendArray as $value){
        $n++;
        $idealXArray[] = 'Day '.$n;
    }




    $datesArray = range(strtotime($project->getStartTime())/(60 * 60 * 24), strtotime($project->getFinishTime())/(60 * 60 * 24), 1);



    $dataArray = array_combine($idealTrendArray, $datesArray);


    $length = count($idealTrendArray);
    $dataArray = range(0, $length, 1);

    for($n = 0; $n<$length; $n++) {

        $doneSize = 0;
        if ($datesArray[$n])
        $dataArray[$n] = [$idealTrendArray[$n], $datesArray[$n]];

    }

    $n = 1;
    $test = $idealTrendArray[$n];





    $hours = $hourManager->getAllHours();
    $groups = $projectManager->getGroups($projectName);
    $groupFromUserAndGroups = $projectManager->getGroupFromUserAndGroups($projectName); //henter gruppe basert på UsersAndGroups tabell. Joiner Group tabell og sjekker prosjektname

    $totalTimeWorked = $hourManager->totalTimeWorked($projectName);


    echo $twig->render('report_print.twig',
        array('session' => $session, 'request' => $request, 'user' => $user,

            'users' => $users,
            'customers' => $customers, 'members' => $members,
            'employees' => $employees, 'candidates' => $candidates,

            'project' => $project,
            'totalTimeWorked' => $totalTimeWorked,
            'phases' => $phases, 'tasks' => $tasks,
            'hours' => $hours,
            'groups' => $groups,
            'groupFromUserAndGroups' => $groupFromUserAndGroups,
            'hourManager' => $hourManager,
            'progressData' => $progressData,
            'idealTrendArray' => $idealTrendArray,
            'datesArray' => $datesArray,
            'length' => $length,
            'idealXArray' => $idealXArray,
            'sumEstimate' => $sumEstimate,
            'actualBurn' => $actualBurn));
} else {
    header("location: index.php");
    exit();
}
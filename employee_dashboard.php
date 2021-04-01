<?php


require_once "includes.php";

define('FILENAME_TAG', 'image');

$user = $session->get('User');


$hourManager = new HourManager($db, $request, $session);
$userManager = new UserManager($db, $request, $session);
$taskManager = new TaskManager($db, $request, $session);
$tasks = $taskManager->getAllTasks();


if ($user) {
    $userID = $user->getUserId($user);
    $hour = $hourManager->getLastHoursForUser($userID);

    echo $twig->render('employee_dashboard.twig',
        array('Hour' => $hour, 'HourManager' => $hourManager, 'UserID' => $userID, 'session' => $session, 'user' => $user, 'tasks' => $tasks));

} else {
    header("location: login.php");
    exit();
}




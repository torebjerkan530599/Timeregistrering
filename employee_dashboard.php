<?php

require_once "includes.php";

define('FILENAME_TAG', 'image');


if (!empty($twig)) {
    try {
        echo $twig->render('employee_dashboard.twig');
    } catch (LoaderError | RuntimeError | SyntaxError $e) {
    }
}

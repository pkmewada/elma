<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/auth-functions.php';
require_once __DIR__ . '/../app/controllers/CandidateAuthController.php';

$controller = new CandidateAuthController();
$controller->logout();
<?php

require_once __DIR__ . '/../app/controllers/CandidateAuthController.php';

$controller = new CandidateAuthController();
$controller->resetForgotPassword();
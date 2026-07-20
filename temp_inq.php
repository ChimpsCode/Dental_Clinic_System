<?php
session_start();
$_SESSION['user_id'] = 7;
$_SESSION['role'] = 'user';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['action' => 'create', 'concern' => 'Test inquiry from CLI'];
include 'master/shared/inquiry_actions.php';

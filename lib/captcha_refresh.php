<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$force = isset($_GET['force']) && $_GET['force'] === '1';

if ($force || !isset($_SESSION['captcha_code']) || !isset($_SESSION['captcha_time']) || (time() - $_SESSION['captcha_time'] > 120)) {
    $_SESSION['captcha_code'] = strval(rand(100000, 999999));
    $_SESSION['captcha_time'] = time();
}

header('Content-Type: text/plain');
echo $_SESSION['captcha_code'];
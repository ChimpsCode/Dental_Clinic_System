<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$noCacheHeaders = [
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0',
    'Pragma: no-cache',
    'Expires: 0',
];
foreach ($noCacheHeaders as $header) {
    header($header);
}

$force = isset($_GET['force']) && $_GET['force'] === '1';

if ($force || !isset($_SESSION['captcha_code']) || !isset($_SESSION['captcha_time']) || (time() - $_SESSION['captcha_time'] > 120)) {
    $_SESSION['captcha_code'] = strval(rand(100000, 999999));
    $_SESSION['captcha_time'] = time();
}

header('Content-Type: text/plain');
echo $_SESSION['captcha_code'];

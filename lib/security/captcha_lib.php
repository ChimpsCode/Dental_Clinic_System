<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function generateCaptcha() {
    $code = strval(rand(100000, 999999));
    $_SESSION['captcha_code'] = $code;
    $_SESSION['captcha_time'] = time();
    return $code;
}

function validateCaptcha($input) {
    if (!isset($_SESSION['captcha_code']) || !isset($_SESSION['captcha_time'])) {
        return false;
    }

    $expired = time() - $_SESSION['captcha_time'] > 120;
    if ($expired) {
        unset($_SESSION['captcha_code'], $_SESSION['captcha_time']);
        return false;
    }

    $valid = $_SESSION['captcha_code'] === $input;
    if (!$valid) {
        unset($_SESSION['captcha_code'], $_SESSION['captcha_time']);
    }
    return $valid;
}

function isCaptchaExpired() {
    if (!isset($_SESSION['captcha_time'])) {
        return true;
    }
    return (time() - $_SESSION['captcha_time']) > 120;
}

function refreshCaptcha() {
    return generateCaptcha();
}

if (!isset($_SESSION['captcha_code'])) {
    generateCaptcha();
}
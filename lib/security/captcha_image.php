<?php
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$force = isset($_GET['force']) && $_GET['force'] === '1';

if ($force || !isset($_SESSION['captcha_code']) || !isset($_SESSION['captcha_time']) || (time() - $_SESSION['captcha_time'] > 120)) {
    $_SESSION['captcha_code'] = strval(rand(100000, 999999));
    $_SESSION['captcha_time'] = time();
}

$code = $_SESSION['captcha_code'];

$width = 150;
$height = 50;

if (function_exists('imagecreatetruecolor') && function_exists('imagepng')) {
    header('Content-Type: image/png');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    $image = imagecreatetruecolor($width, $height);
    
    $bg = imagecolorallocate($image, 235, 235, 235);
    imagefill($image, 0, 0, $bg);
    
    $fg = imagecolorallocate($image, 40, 40, 40);
    
    for ($i = 0; $i < 10; $i++) {
        $c = imagecolorallocate($image, rand(200, 230), rand(200, 230), rand(200, 230));
        imageline($image, rand(0, $width), rand(0, $height), rand(0, $width), rand(0, $height), $c);
    }
    
    for ($i = 0; $i < 50; $i++) {
        $c = imagecolorallocate($image, rand(200, 230), rand(200, 230), rand(200, 230));
        imagesetpixel($image, rand(0, $width), rand(0, $height), $c);
    }
    
    $font = 'C:\Windows\Fonts\arial.ttf';
    if (!file_exists($font)) {
        $font = 'C:\xampp\htdocs\Dental_Clinic_System\assets\fonts\arial.ttf';
    }
    
    if (file_exists($font) && function_exists('imagettftext')) {
        $x = 10;
        for ($i = 0; $i < strlen($code); $i++) {
            imagettftext($image, 20, rand(-10, 10), $x, rand(30, 35), $fg, $font, $code[$i]);
            $x += 22;
        }
    } else {
        imagestring($image, 4, 20, 15, $code, $fg);
    }
    
    imagepng($image);
    imagedestroy($image);
} else {
    header('Content-Type: image/png');
    $image = imagecreate($width, $height);
    $bg = imagecolorallocate($image, 235, 235, 235);
    $fg = imagecolorallocate($image, 40, 40, 40);
    imagestring($image, 4, 20, 15, $code, $fg);
    imagepng($image);
    imagedestroy($image);
}
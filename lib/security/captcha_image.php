<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../includes/session_bootstrap.php';

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
    header('Content-Type: image/svg+xml');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');

    $noiseLines = '';
    for ($i = 0; $i < 10; $i++) {
        $c = sprintf('rgb(%d,%d,%d)', rand(200, 230), rand(200, 230), rand(200, 230));
        $noiseLines .= sprintf(
            '<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="%s"/>',
            rand(0, $width), rand(0, $height), rand(0, $width), rand(0, $height), $c
        );
    }

    $texts = '';
    $x = 12;
    $len = strlen($code);
    for ($i = 0; $i < $len; $i++) {
        $rot = rand(-12, 12);
        $y = rand(30, 38);
        $char = htmlspecialchars($code[$i], ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $texts .= sprintf(
            '<text x="%d" y="%d" transform="rotate(%d %d %d)" font-family="Arial, sans-serif" font-size="20" font-weight="bold" fill="#282828">%s</text>',
            $x, $y, $rot, $x, $y, $char
        );
        $x += 22;
    }

    echo '<?xml version="1.0" encoding="UTF-8"?>'
        . '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '">'
        . '<rect width="100%" height="100%" fill="#ebebeb"/>'
        . $noiseLines
        . $texts
        . '</svg>';
}
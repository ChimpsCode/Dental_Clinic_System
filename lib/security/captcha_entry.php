<?php
session_start();

header('Content-Type: image/png');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Generate code
$code = '';
$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
for ($i = 0; $i < 6; $i++) {
    $code .= $chars[random_int(0, strlen($chars) - 1)];
}

// Store in session
$_SESSION['captcha_code'] = $code;

// Create image
$width = 150;
$height = 40;
$image = imagecreate($width, $height);

// Colors
$bg = imagecolorallocate($image, 245, 245, 245);
$text_color = imagecolorallocate($image, 0, 89, 163);
$line_color = imagecolorallocate($image, 200, 200, 200);

// Draw lines for noise
for ($i = 0; $i < 5; $i++) {
    imageline($image, 0, random_int(0, $height), $width, random_int(0, $height), $line_color);
}

// Draw text
imagestring($image, 5, 25, 12, $code, $text_color);

// Output
imagepng($image);
imagedestroy($image);

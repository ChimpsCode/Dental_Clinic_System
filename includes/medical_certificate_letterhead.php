<?php

function medicalCertificateLetterhead(PDO $pdo): array
{
    $clinic = ['name' => 'RF Dental Clinic', 'address' => '', 'phone' => '', 'logo' => '', 'watermark' => ''];
    $logoPath = 'assets/images/Logo.png';
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('clinic_name', 'clinic_address', 'clinic_phone', 'welcome_logo')");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $setting) {
            if ($setting['setting_key'] === 'welcome_logo') {
                $logoPath = $setting['setting_value'] ?: $logoPath;
            } else {
                $clinic[substr($setting['setting_key'], 7)] = $setting['setting_value'];
            }
        }
    } catch (Exception $e) {
        // Keep the clinic's default letterhead when settings are unavailable.
    }

    // Embed the logo so downloaded certificates also work offline.
    $root = realpath(dirname(__DIR__));
    foreach ([$logoPath, 'assets/images/Logo.png'] as $candidate) {
        $path = realpath($root . '/' . ltrim($candidate, '/\\'));
        if (!$path || stripos($path, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
            continue;
        }
        $info = @getimagesize($path);
        if (!$info || !in_array($info['mime'], ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true)) {
            continue;
        }
        $data = @file_get_contents($path);
        if ($data !== false) {
            $clinic['logo'] = 'data:' . $info['mime'] . ';base64,' . base64_encode($data);
            $clinic['watermark'] = medicalCertificateWatermark($data);
            break;
        }
    }
    return $clinic;
}

function medicalCertificateWatermark(string $logoData): string
{
    if (!function_exists('imagecreatefromstring')) {
        return '';
    }
    $source = @imagecreatefromstring($logoData);
    if (!$source) {
        return '';
    }
    // A small, transparent bitmap keeps the downloadable PDF compact. Blur only
    // the background copy; the letterhead's original logo stays sharp.
    $size = 440;
    $canvas = imagecreatetruecolor($size, $size);
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 255, 255, 255, 127));
    $scale = ($size - 32) / max(imagesx($source), imagesy($source));
    $width = (int)round(imagesx($source) * $scale);
    $height = (int)round(imagesy($source) * $scale);
    imagecopyresampled($canvas, $source, (int)(($size - $width) / 2), (int)(($size - $height) / 2), 0, 0, $width, $height, imagesx($source), imagesy($source));
    for ($pass = 0; $pass < 8; $pass++) {
        imagefilter($canvas, IMG_FILTER_GAUSSIAN_BLUR);
    }
    ob_start();
    imagepng($canvas);
    $blurred = ob_get_clean();
    imagedestroy($source);
    imagedestroy($canvas);
    return $blurred !== false ? 'data:image/png;base64,' . base64_encode($blurred) : '';
}

<?php
/**
 * Validate uploaded images by real MIME type. Never trust $_FILES['type']
 * or the original filename extension.
 */
function validate_safe_image_upload(array $file, int $maxBytes = 2097152): array
{
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'No file uploaded or upload error.', 'mime' => '', 'ext' => ''];
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > $maxBytes) {
        return ['ok' => false, 'error' => 'File size is invalid or too large.', 'mime' => '', 'ext' => ''];
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'error' => 'Invalid upload.', 'mime' => '', 'ext' => ''];
    }

    if (!class_exists('finfo')) {
        return ['ok' => false, 'error' => 'Server cannot verify image type.', 'mime' => '', 'ext' => ''];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmp);
    $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    if (!isset($map[$mime])) {
        return ['ok' => false, 'error' => 'Only JPG, PNG, GIF, and WebP images are allowed.', 'mime' => '', 'ext' => ''];
    }

    return ['ok' => true, 'error' => null, 'mime' => $mime, 'ext' => $map[$mime]];
}

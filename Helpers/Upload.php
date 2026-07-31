<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Config;

/**
 * Hardened file upload handling: extension + MIME + size validation and
 * randomized file names. Executable types are rejected outright.
 */
final class Upload
{
    /**
     * @param array $file One entry from $_FILES
     * @return array{0: bool, 1: string} [ok, storedRelativePath|errorMessage]
     */
    public static function store(array $file, string $subdir): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return [false, 'File upload failed.'];
        }
        if ($file['size'] > Config::get('app.uploads.max_bytes')) {
            return [false, 'File exceeds the maximum allowed size.'];
        }

        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, Config::get('app.uploads.allowed_ext'), true)) {
            return [false, 'File type is not allowed.'];
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: '';
        if (!in_array($mime, Config::get('app.uploads.allowed_mime'), true)) {
            SecurityLogger::log('upload_rejected', 'medium', "Rejected upload with MIME '$mime'");
            return [false, 'File content does not match an allowed type.'];
        }

        $subdir = preg_replace('/[^a-z0-9_\-]/', '', strtolower($subdir)) ?: 'misc';
        $dir = Config::get('app.uploads.path') . '/' . $subdir;
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            return [false, 'Could not store the uploaded file.'];
        }
        return [true, 'uploads/' . $subdir . '/' . $name];
    }
}

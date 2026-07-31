<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Response helpers. Every API answer uses the consistent envelope:
 * { "success": bool, "message": string, "data": {} }.
 */
final class Response
{
    public static function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function ok(string $message, array $data = []): never
    {
        self::json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    public static function fail(string $message, int $status = 400, array $data = []): never
    {
        self::json(['success' => false, 'message' => $message, 'data' => $data], $status);
    }

    public static function redirect(string $to): never
    {
        header('Location: ' . $to);
        exit;
    }

    public static function viewError(int $code): never
    {
        http_response_code($code);
        $file = BASE_PATH . "/app/Views/errors/{$code}.php";
        if (is_file($file)) {
            require $file;
        } else {
            echo "Error {$code}";
        }
        exit;
    }

    public static function download(string $filePath, string $downloadName, string $mime): never
    {
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . (string) filesize($filePath));
        readfile($filePath);
        exit;
    }
}

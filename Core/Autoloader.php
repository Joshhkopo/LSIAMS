<?php

declare(strict_types=1);

namespace App\Core;

/**
 * PSR-4 style autoloader mapping the App\ namespace onto the app/ directory.
 * Keeps the system dependency-free (no Composer required on the LAN server).
 */
final class Autoloader
{
    public static function register(): void
    {
        spl_autoload_register(static function (string $class): void {
            if (!str_starts_with($class, 'App\\')) {
                return;
            }
            $relative = substr($class, 4); // strip "App\"
            $file = BASE_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require $file;
            }
        });
    }
}

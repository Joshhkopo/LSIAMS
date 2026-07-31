<?php

declare(strict_types=1);

namespace App\Core;

use App\Helpers\Auth;
use App\Helpers\Csrf;

/**
 * Very small view renderer. Views are plain PHP templates under app/Views.
 * `e()` is available in every template for output escaping (XSS protection).
 */
final class View
{
    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $template, array $data = [], string $layout = 'layouts/main'): never
    {
        $viewFile = BASE_PATH . '/app/Views/' . $template . '.php';
        if (!is_file($viewFile)) {
            Response::viewError(500);
        }

        $e = static fn(mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        $csrfToken = Csrf::token();
        $currentUser = Auth::user();
        $flashes = Session::pullFlashes();

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if ($layout !== '') {
            $layoutFile = BASE_PATH . '/app/Views/' . $layout . '.php';
            require $layoutFile;
        } else {
            echo $content;
        }
        exit;
    }
}

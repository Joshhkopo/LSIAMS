<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Helpers\Csrf;
use App\Helpers\SecurityLogger;

/**
 * Verifies the CSRF token on state-changing web requests. Accepts either
 * the `_csrf` form field or the `X-CSRF-Token` header (AJAX).
 */
final class CsrfMiddleware
{
    public function handle(Request $request): void
    {
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        $token = $request->input('_csrf') ?? $request->header('x-csrf-token');
        if (Csrf::validate(is_string($token) ? $token : null)) {
            return;
        }

        SecurityLogger::log('csrf_failure', 'high', 'Invalid CSRF token on ' . $request->path(), $request->ip());

        if ($request->wantsJson()) {
            Response::json(['success' => false, 'message' => 'Security token mismatch. Refresh the page and try again.'], 419);
        }
        Response::viewError(403);
    }
}

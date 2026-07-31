<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Base controller with response conveniences shared by web and API layers.
 */
abstract class Controller
{
    protected function view(string $template, array $data = [], string $layout = 'layouts/main'): never
    {
        View::render($template, $data, $layout);
    }

    protected function json(array $payload, int $status = 200): never
    {
        Response::json($payload, $status);
    }

    protected function ok(string $message, array $data = []): never
    {
        Response::ok($message, $data);
    }

    protected function fail(string $message, int $status = 400, array $data = []): never
    {
        Response::fail($message, $status);
    }

    protected function redirect(string $to): never
    {
        Response::redirect($to);
    }

    /** Standard pagination inputs, clamped to safe bounds. */
    protected function paging(Request $request): array
    {
        $perPage = (int) $request->query('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;
        $page = max(1, (int) $request->query('page', 1));
        return [$perPage, ($page - 1) * $perPage, $page];
    }
}

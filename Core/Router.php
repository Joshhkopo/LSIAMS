<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\DeviceAuthMiddleware;
use App\Middleware\RoleMiddleware;

/**
 * Minimal HTTP router with {param} placeholders and named middleware.
 *
 * Middleware names:
 *   auth               logged-in web user required
 *   role:Administrator specific role required (comma-separate for several)
 *   csrf               CSRF token required on state-changing requests
 *   device             authenticated ESP32 device required (API key + nonce)
 */
final class Router
{
    /** @var array<int, array{method:string, pattern:string, regex:string, handler:array, middleware:array}> */
    private array $routes = [];

    public function add(string $method, string $pattern, array $handler, array $middleware = []): void
    {
        $regex = '#^' . preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', rtrim($pattern, '/')) . '/?$#';
        $this->routes[] = [
            'method'     => strtoupper($method),
            'pattern'    => $pattern,
            'regex'      => $regex,
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    public function get(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    public function dispatch(Request $request): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method()) {
                continue;
            }
            if (!preg_match($route['regex'], $request->path(), $matches)) {
                continue;
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $request->setRouteParams($params);

            foreach ($route['middleware'] as $name) {
                $this->runMiddleware($name, $request);
            }

            [$class, $action] = $route['handler'];
            $controller = new $class();
            $controller->$action($request);
            return;
        }

        if ($request->isApi() || $request->wantsJson()) {
            Response::json(['success' => false, 'message' => 'Endpoint not found.'], 404);
        }
        Response::viewError(404);
    }

    private function runMiddleware(string $name, Request $request): void
    {
        if ($name === 'auth') {
            (new AuthMiddleware())->handle($request);
        } elseif (str_starts_with($name, 'role:')) {
            (new RoleMiddleware(explode(',', substr($name, 5))))->handle($request);
        } elseif ($name === 'csrf') {
            (new CsrfMiddleware())->handle($request);
        } elseif ($name === 'device') {
            (new DeviceAuthMiddleware())->handle($request);
        }
    }
}

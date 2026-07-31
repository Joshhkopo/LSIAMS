<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Immutable-ish wrapper around the current HTTP request. JSON bodies are
 * decoded once; route params and authenticated device context are attached
 * by the router / middleware.
 */
final class Request
{
    /** @var array<string, mixed> */
    private array $routeParams = [];

    /** @var array<string, mixed>|null Device row attached by DeviceAuthMiddleware */
    private ?array $device = null;

    private function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $body,
        private readonly array $headers,
        private readonly string $ip,
    ) {
    }

    public static function capture(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $path = '/' . trim($path, '/');

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[str_replace('_', '-', strtolower(substr($key, 5)))] = $value;
            }
        }

        $body = $_POST;
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode(file_get_contents('php://input') ?: '', true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path === '' ? '/' : $path,
            $_GET,
            $body,
            $headers,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function isApi(): bool
    {
        return str_starts_with($this->path, '/api/');
    }

    public function wantsJson(): bool
    {
        return str_contains($this->header('accept') ?? '', 'application/json')
            || strtolower($this->header('x-requested-with') ?? '') === 'xmlhttprequest';
    }

    public function ip(): string
    {
        return $this->ip;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function userAgent(): string
    {
        return substr($this->header('user-agent') ?? 'unknown', 0, 255);
    }

    /** Body field (POST or JSON). */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /** Query-string field. */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->body;
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function setDevice(array $device): void
    {
        $this->device = $device;
    }

    /** @return array<string, mixed>|null */
    public function device(): ?array
    {
        return $this->device;
    }
}

<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Helpers\Auth;
use App\Helpers\SecurityLogger;

/**
 * Strict RBAC gate: the authenticated user's role must be in the allowed
 * list. Violations return HTTP 403 and generate a security log entry.
 */
final class RoleMiddleware
{
    /** @param string[] $allowedRoles */
    public function __construct(private readonly array $allowedRoles)
    {
    }

    public function handle(Request $request): void
    {
        $role = Auth::role();
        if ($role !== null && in_array($role, $this->allowedRoles, true)) {
            return;
        }

        SecurityLogger::log(
            'permission_violation',
            'high',
            sprintf('User #%s (%s) attempted %s %s', Auth::id() ?? '-', $role ?? 'guest', $request->method(), $request->path()),
            $request->ip(),
        );

        if ($request->wantsJson()) {
            Response::json(['success' => false, 'message' => 'You do not have permission to perform this action.'], 403);
        }
        Response::viewError(403);
    }
}

<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Helpers\Auth;

/**
 * Requires an authenticated web session. AJAX callers get a JSON 401;
 * browser navigation is redirected to the login screen.
 */
final class AuthMiddleware
{
    public function handle(Request $request): void
    {
        if (Auth::check()) {
            return;
        }
        if ($request->wantsJson()) {
            Response::json(['success' => false, 'message' => 'Session expired. Please log in again.'], 401);
        }
        Session::flash('warning', 'Please log in to continue.');
        Response::redirect('/login');
    }
}

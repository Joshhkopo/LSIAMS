<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Session;

/**
 * Session-bound CSRF tokens. The token is embedded in every form and also
 * exposed to JS (X-CSRF-Token header) for AJAX requests.
 */
final class Csrf
{
    public static function token(): string
    {
        $token = Session::get('_csrf');
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set('_csrf', $token);
        }
        return $token;
    }

    public static function validate(?string $token): bool
    {
        $expected = Session::get('_csrf');
        return is_string($token) && is_string($expected) && $expected !== ''
            && hash_equals($expected, $token);
    }
}

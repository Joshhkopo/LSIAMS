<?php

declare(strict_types=1);

namespace App\Core;

use App\Helpers\Logger;
use Throwable;

/**
 * Application kernel: loads configuration, boots the session for web
 * requests, registers routes and dispatches the current request.
 */
final class App
{
    private Router $router;
    private Request $request;

    public function __construct()
    {
        Config::load();
        date_default_timezone_set(Config::get('app.timezone'));

        $this->request = Request::capture();
        $this->router  = new Router();

        // API routes are stateless; web routes use the secure session.
        if (!$this->request->isApi()) {
            Session::start();
        }

        $registerWeb = require BASE_PATH . '/routes/web.php';
        $registerApi = require BASE_PATH . '/routes/api.php';
        $registerWeb($this->router);
        $registerApi($this->router);
    }

    public function run(): void
    {
        try {
            $this->router->dispatch($this->request);
        } catch (Throwable $e) {
            Logger::error('Unhandled exception: ' . $e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'path'  => $this->request->path(),
            ]);

            if ($this->request->isApi() || $this->request->wantsJson()) {
                Response::json([
                    'success' => false,
                    'message' => 'An internal error occurred. The event has been logged.',
                ], 500);
            } else {
                Response::viewError(500);
            }
        }
    }
}

<?php

declare(strict_types=1);

/**
 * L-SIAMS front controller.
 * Every web and API request is rewritten here (see public/.htaccess).
 */

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Core/Autoloader.php';

App\Core\Autoloader::register();

(new App\Core\App())->run();

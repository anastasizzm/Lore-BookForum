<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../config/settings.php';

use App\Lib\Settings;
$settings = new Settings($config);
View::configure($settings);

$router = new Router();
require __DIR__ . '/../config/routes.php';

try {
    $request = Request::fromGlobals();
    $kernel = new Kernel($router, $settings);
    $kernel->handle($request)->send();
} catch (HttpException $e) {
    Response::json(['error' => $e->getMessage()], $e->getStatus())->send();
}
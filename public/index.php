<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config/settings.php';

use App\Lib\Settings;
use App\Lib\View;
use App\Lib\Container;
use App\Http\Router;
use App\Http\Request;
use App\Http\Response;
use App\Http\HttpException;
use App\Kernel;

$settings = new Settings($config);
$container = new Container();
require __DIR__ . '/../config/dependencies.php';
View::configure($container);

$router = new Router($container);
$routeLoader = require __DIR__ . '/../config/routes.php';
$routeLoader($router);

try {
    $request = Request::fromGlobals();
    $kernel = new Kernel($router, $container);
    $kernel->handle($request)->send($request->method);
} catch (HttpException $e) {
    Response::json(['error' => $e->getMessage()], $e->getStatus())->send();
}

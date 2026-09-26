<?php

use App\Http\Router;
use App\Http\Request;
use App\Http\Response;

return function(Router $router)
{
    $router->get('/test', function (Request $request) {
        return Response::html("<h1>Test completed successfully</h1>");
    }, 'test_route');

    // Auth
    $router->get('/login', [App\Controllers\AuthController::class, 'getLogin'], 'login', [App\Middleware\AuthMiddleware::class]);
    $router->post('/login', [App\Controllers\AuthController::class, 'login'], NULL, [App\Middleware\AuthMiddleware::class]);

    $router->get('/register', [App\Controllers\AuthController::class, 'getRegister'], 'register', [App\Middleware\AuthMiddleware::class]);
    $router->post('/register', [App\Controllers\AuthController::class, 'register'], NULL, [App\Middleware\AuthMiddleware::class]);

    $router->get('/verify/{token}', [App\Controllers\AuthController::class, 'mailVerify'], NULL, [App\Middleware\AuthMiddleware::class]);
};
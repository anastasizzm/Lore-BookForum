<?php

use App\Http\Router;
use App\Http\Request;
use App\Http\Response;

use App\Lib\View;

return function(Router $router)
{
    $router->get('/pages/test', function (Request $request){
        $page = $request->getQuery('page');
        if (!is_string($page)) return Response::html("<p>Provide page name in ?page query</p>");

        return Response::html(View::render($page, $request->query));
    }, 'test_pages');

    $router->get('/', function (Request $request) {
        return Response::html("<h1>Test completed successfully</h1>");
    }, 'home');

    // Auth
    $router->get('/login', [App\Controllers\Auth\AuthController::class, 'getLogin'], 'login', [App\Middleware\AuthMiddleware::class]);
    $router->post('/login', [App\Controllers\Auth\AuthController::class, 'login'], 'login.submit', [App\Middleware\AuthMiddleware::class]);

    $router->get('/register', [App\Controllers\Auth\AuthController::class, 'getRegister'], 'register', [App\Middleware\AuthMiddleware::class]);
    $router->post('/register', [App\Controllers\Auth\AuthController::class, 'register'], 'register.submit', [App\Middleware\AuthMiddleware::class]);

    $router->post('/logout', [App\Controllers\Auth\AuthController::class, 'logout'], 'logout');

    $router->get('/verify/{token}', [App\Controllers\Auth\AuthController::class, 'mailVerify'], 'verify.mail', [App\Middleware\AuthMiddleware::class]);
};
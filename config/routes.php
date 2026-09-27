<?php

use App\Http\Router;
use App\Http\Request;
use App\Http\Response;

use App\Lib\Auth\AuthPolicy;
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
    }, 'home', AuthPolicy::Verified);

    // Auth
    $router->get('/login', [App\Controllers\Auth\AuthController::class, 'getLogin'], 'login', AuthPolicy::Public);
    $router->post('/login', [App\Controllers\Auth\AuthController::class, 'login'], 'login.submit', AuthPolicy::Public);

    $router->get('/register', [App\Controllers\Auth\AuthController::class, 'getRegister'], 'register', AuthPolicy::Public);
    $router->post('/register', [App\Controllers\Auth\AuthController::class, 'register'], 'register.submit', AuthPolicy::Public);

    $router->post('/logout', [App\Controllers\Auth\AuthController::class, 'logout'], 'logout', AuthPolicy::Auth);

    $router->get('/verify/{token}', [App\Controllers\Auth\AuthController::class, 'mailVerify'], 'verify.mail', AuthPolicy::Public);
};
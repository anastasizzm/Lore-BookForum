<?php

use App\Http\Router;
use App\Http\Request;
use App\Http\Response;

return function(Router $router)
{
    $router->get('/test', function (Request $request) {
        return Response::html("<h1>Test completed successfully</h1>");
    }, 'test_route');
};
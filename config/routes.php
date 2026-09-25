<?php

use App\Http\Router;
use App\Http\Request;
use App\Http\Response;

return function(Router $router)
{
    $router->get('/test', function (Request $request) {
        return Response::text("Test completed");
    }, 'test_route');
};
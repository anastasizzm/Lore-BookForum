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

    $router->get('/test', function (Request $request) {
        return Response::html("<h1>Test completed successfully</h1>");
    }, 'test_route');
};
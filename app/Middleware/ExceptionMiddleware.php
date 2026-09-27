<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Lib\View;

use App\Http\HttpException;
use App\Http\Middleware;
use App\Http\Request;
use App\Http\Response;
use App\Http\UrlGenerator;

final class ExceptionMiddleware implements Middleware
{
    public function __construct(private readonly UrlGenerator $url){}

    public function handle(Request $request, callable $next): Response
    {
        try{
            return $next($request);
        }
        catch(HttpException $e){
            return Response::html(View::render('message', ['statusCode' => $e->getStatus(), 'message' => $e->getMessage(), 'actionUrl' => $this->url->url('home'), 'actionTitle' => 'Continue']));
        }
    }
}
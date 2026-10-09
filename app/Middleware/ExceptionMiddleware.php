<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Lib\View;

use App\Http\HttpException;
use App\Http\Middleware;
use App\Http\HttpContext;
use App\Http\Response;
use App\Http\UrlGenerator;

use App\ErrorCodes;

use App\Models\Errors\Error;

use App\Exceptions\ValidationException;
use App\Extensions\ResponseTemplates;

final class ExceptionMiddleware implements Middleware
{
    public function __construct(
        private readonly UrlGenerator $url
    ){}

    public function handle(HttpContext $ctx, callable $next): Response
    {
        try{
            return $next($ctx);
        }
        catch(HttpException $e){
            if ($ctx->isApi())
                return Response::json(ResponseTemplates::error($e->toError()), $e->getStatus());
            else return Response::html(View::render('message', ['statusCode' => $e->getStatus(), 'message' => $e->getMessage(), 'actionUrl' => $this->url->url('home'), 'actionTitle' => 'To Home']));
        }
        catch(ValidationException $e)
        {
            if ($ctx->isApi())
                return Response::json(ResponseTemplates::error($e->toError()), 422);
            else return Response::html(View::render('message', [
                'statusCode' => 422, 
                'message' => 'Some validation errors occured',
                'innerMessages' => $e->toMessages()
            ]));
        }
    }
}
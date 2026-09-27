<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\AuthorizationService;

use App\Lib\Auth\Decision;
use App\Lib\View;

use App\Http\HttpContext;
use App\Http\Middleware;
use App\Http\Response;
use App\Http\UrlGenerator;

final class AuthorizationMiddleware implements Middleware
{
    public function __construct(
        private readonly AuthorizationService $authz,
        private readonly UrlGenerator         $url,
    ) {}

    public function handle(HttpContext $ctx, callable $next): Response
    {
        $decision = $this->authz->authorize($ctx->route->authPolicy, $ctx);

        if ($decision->allowed) {
            return $next($ctx);
        }

        return $this->deny($ctx, $decision);
    }

    private function deny(HttpContext $ctx, Decision $decision): Response
    {
        if ($ctx->request->isApi()) {
            return Response::json([
                'errors' => [
                    [
                        'errorCode' => $decision->errorCode,
                        'message' => $decision->message
                    ]
                ],
                'code'  => $decision->status,
                'message' => 'Forbidden',
            ], $decision->status);
        }

        return match ($decision->status) {
            401     => Response::redirect($this->url->url('login'), 303),
            404     => Response::html(View::render('message', ['statusCode' => 404, 'message' => "Not Found. $decision->message. ERR_CODE: $decision->errorCode"]), 404),
            default => Response::html(View::render('message', ['statusCode' => $decision->status, 'message' => "Forbidden. $decision->message. ERR_CODE: $decision->errorCode"]), $decision->status),
        };
    }
}
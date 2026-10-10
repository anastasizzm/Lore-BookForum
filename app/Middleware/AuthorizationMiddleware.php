<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Services\Auth\AuthorizationService;

use App\Lib\Auth\Decision;
use App\Lib\I18n\Translator;
use App\Lib\View;

use App\Http\HttpContext;
use App\Http\Middleware;
use App\Http\Response;
use App\Http\UrlGenerator;

use App\Extensions\ResponseTemplates;

use App\Models\Errors\Error;

final class AuthorizationMiddleware implements Middleware
{
    public function __construct(
        private readonly AuthorizationService $authz,
        private readonly UrlGenerator $url,
        private readonly Translator $translator
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
        if ($ctx->isApi()) {
            return Response::json(ResponseTemplates::error(new Error($decision->errorCode, $this->translator->t($decision->reason))), $decision->status);
        }

        return match ($decision->status) {
            401     => Response::redirect($this->url->url('login'), 303),
            404     => Response::html(View::render('message', ['statusCode' => 404, 'message' => $this->buildMessage("Not Found", $decision->reason, $decision->errorCode)]), 404),
            default => Response::html(View::render('message', ['statusCode' => $decision->status, 'message' => $this->buildMessage("Forbidden", $decision->reason, $decision->errorCode)]), $decision->status),
        };
    }

    private function buildMessage(string $start, string $decisionKey, string $errorCode) : string 
    {
        $translated = $this->translator->t($decisionKey);
        return "$start. $translated. ERR_CODE: $errorCode";
    }
}
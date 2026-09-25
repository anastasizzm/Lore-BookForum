<?php
declare(strict_types=1);

namespace App\Services;

use App\Http\Request;
use App\Http\Response;

final class CookieService
{
    public function __construct(
        private readonly string $sameSite = 'Lax',
        private readonly bool $secureByDefault = true,
    ) {}

    public function get(Request $request, string $name): ?string
    {
        return $request->getCookie($name);
    }

    public function set(
        Response $response,
        string $name,
        string $value,
        int $maxAge = 0,
        ?bool $secure = null
    ): Response {
        $cookie = new Cookie(
            name:     $name,
            value:    $value,
            maxAge:   $maxAge,
            secure:   $secure === null ? $this->secureByDefault : $secure,
            httpOnly: true,
            sameSite: $this->sameSite,
        );

        return $response->withHeader('Set-Cookie', $cookie->toHeader());
    }

    public function clear(
        Response $response,
        string $name,
        ?bool $secure = null
    ): Response {
        $cookie = new Cookie(
            name:     $name,
            value:    '',
            maxAge:   0,
            secure:   $secure === null ? $this->secureByDefault : $secure,
            httpOnly: true,
            sameSite: $this->sameSite,
        );

        return $response->withHeader('Set-Cookie', $cookie->expired()->toHeader());
    }
}
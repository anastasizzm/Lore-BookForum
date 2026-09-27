<?php
declare(strict_types=1);

namespace App\Http;

use App\Http\HttpContext;
use App\Http\Response;

interface Middleware
{
    public function handle(HttpContext $ctx, callable $next): Response;
}
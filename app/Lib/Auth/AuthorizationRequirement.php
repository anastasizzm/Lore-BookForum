<?php
declare(strict_types=1);

namespace App\Lib\Auth;

interface AuthorizationRequirement
{
    /** Human-readable description, used in deny messages and logs. */
    public function describe(): string;
}
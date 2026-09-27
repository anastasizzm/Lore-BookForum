<?php
declare(strict_types=1);

namespace App\Lib\Auth;

enum AuthPolicy : string
{
    case Public    = 'public';
    case Auth     = 'auth';
    case Verified = 'verified';
    case Admin    = 'admin';
}
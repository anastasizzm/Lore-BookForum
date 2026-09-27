<?php
declare(strict_types=1);

namespace App;

final class Constants
{
    public const TOKEN_COOKIE = 'access_token';
    public const CSRF_COOKIE = 'csrf_token';

    public const CSRF_HEADER = 'x-csrf-token';
    public const CSRF_FIELD = '_token';
    
    public const USER_ID_ATTR = 'user_id';
    public const VERIFIED_ATTR = 'is_verified';
    public const CSRF_ATTR = 'csrf';

    public const PROTECTED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];
}
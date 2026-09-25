<?php
declare(strict_types=1);

namespace App;

final class Constants
{
    public const TOKEN_COOKIE = 'access_token';
    
    public const USER_ID_ATTR = 'user_id';
    public const USERNAME_ATTR = 'username';
    public const CSRF_ATTR = 'csrf';

    public const PROTECTED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];
}
<?php
declare(strict_types=1);

namespace App\Lib;

final class Cookies
{
    public static function set(string $name, string $value, int $ttl, bool $httpOnly = true): void
    {
        setcookie($name, $value, [
            'expires'  => $ttl > 0 ? time() + $ttl : 0,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => $httpOnly,
            'samesite' => 'Strict',
        ]);
        $_COOKIE[$name] = $value;
    }

    public static function get(string $name): ?string
    {
        return $_COOKIE[$name] ?? null;
    }

    public static function clear(string $name, bool $httpOnly = true): void
    {
        setcookie($name, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => $httpOnly,
            'samesite' => 'Strict',
        ]);
        unset($_COOKIE[$name]);
    }
}
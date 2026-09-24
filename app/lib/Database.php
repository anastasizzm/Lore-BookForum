<?php
declare(strict_types=1);

namespace App\Lib;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo) return self::$pdo;

        $url = Config::require('DATABASE_URL');
        $p   = parse_url($url);
        if ($p === false || empty($p['host']) || empty($p['path'])) {
            throw new RuntimeException('Malformed DATABASE_URL');
        }

        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            $p['host'],
            $p['port'] ?? 5432,
            ltrim($p['path'], '/')
        );

        if (!empty($p['query'])) {
            parse_str($p['query'], $q);
            foreach (['sslmode', 'connect_timeout', 'application_name'] as $k) {
                if (isset($q[$k])) $dsn .= ";$k={$q[$k]}";
            }
        }

        return self::$pdo = new PDO(
            $dsn,
            rawurldecode($p['user'] ?? ''),
            rawurldecode($p['pass'] ?? ''),
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]
        );
    }
}
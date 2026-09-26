<?php
declare(strict_types=1);

namespace App\Lib;

use PDO;
use RuntimeException;

final class Database
{
    private ?PDO $pdo = null;

    public function __construct(private Settings $settings) {}

    /**
     * Lazy PDO accessor. The connection is opened on first call,
     * then reused for the lifetime of this Database instance.
     */
    public function pdo(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        [$dsn, $user, $pass] = $this->parseUrl($this->settings->databaseUrl);

        return $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
    }

    /**
     * Run a callable inside a transaction.
     * Commits on success, rolls back on any exception.
     */
    public function transaction(callable $fn): mixed
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $result = $fn($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Close the connection (useful for long-running workers). */
    public function close(): void
    {
        $this->pdo = null;
    }

    /**
     * @return array{0: string, 1: string, 2: string}  [$dsn, $user, $pass]
     */
    private function parseUrl(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['host']) || empty($parts['path'])) {
            throw new RuntimeException('Malformed DATABASE_URL');
        }

        $scheme = $parts['scheme'] ?? 'pgsql';

        if (!in_array($scheme, ['pgsql', 'postgres', 'postgresql'], true)) {
            throw new RuntimeException("Unsupported DB scheme: $scheme");
        }

        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            $parts['host'],
            $parts['port'] ?? 5432,
            ltrim($parts['path'], '/'),
        );

        if (!empty($parts['query'])) {
            parse_str($parts['query'], $q);
            foreach (['sslmode', 'connect_timeout', 'application_name'] as $key) {
                if (isset($q[$key])) {
                    $dsn .= ";$key={$q[$key]}";
                }
            }
        }

        return [
            $dsn,
            rawurldecode($parts['user'] ?? ''),
            rawurldecode($parts['pass'] ?? ''),
        ];
    }
}
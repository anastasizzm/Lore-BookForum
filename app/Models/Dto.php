<?php
declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use RuntimeException;

abstract readonly class Dto
{
    /** @param array<string, mixed> $row */
    protected static function str(array $row, string $key): string
    {
        if (!array_key_exists($key, $row) || $row[$key] === null) {
            throw new RuntimeException("Missing required column: $key");
        }
        return (string) $row[$key];
    }

    /** @param array<string, mixed> $row */
    protected static function strN(array $row, string $key): ?string
    {
        $v = $row[$key] ?? null;
        return $v === null ? null : (string) $v;
    }

    /** @param array<string, mixed> $row */
    protected static function int(array $row, string $key): int
    {
        return (int) self::str($row, $key);
    }

    /** @param array<string, mixed> $row */
    protected static function intN(array $row, string $key): ?int
    {
        $v = $row[$key] ?? null;
        return $v === null ? null : (int) $v;
    }

    /** @param array<string, mixed> $row */
    protected static function dt(array $row, string $key): DateTimeImmutable
    {
        return new DateTimeImmutable(self::str($row, $key));
    }

    /** @param array<string, mixed> $row */
    protected static function dtN(array $row, string $key): ?DateTimeImmutable
    {
        $v = $row[$key] ?? null;
        return $v === null ? null : new DateTimeImmutable((string) $v);
    }

    /**
     * True when a prefixed group of columns has at least one non-null value.
     * Use this to detect "the LEFT JOIN returned nothing".
     *
     * @param array<string, mixed> $row
     */
    protected static function hasGroup(array $row, string $prefix, string $idColumn): bool
    {
        return ($row[$prefix . $idColumn] ?? null) !== null;
    }

    public abstract static function fromRow(array $row, string $prefix = '') : self;
}
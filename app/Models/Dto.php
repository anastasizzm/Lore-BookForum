<?php
declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use RuntimeException;
use App\Models\Uuid;

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

    /** @param array<string, mixed> $row */
    protected static function bool(array $row, string $key): bool
    {
        if (!array_key_exists($key, $row) || $row[$key] === null) {
            throw new RuntimeException("Missing required column: $key");
        }

        return self::toBool($row[$key]);
    }

    /** @param array<string, mixed> $row */
    protected static function boolN(array $row, string $key): ?bool
    {
        $v = $row[$key] ?? null;

        return $v === null ? null : self::toBool($v);
    }

    protected static function uuid(array $row, string $key): Uuid
    {
        return Uuid::fromString(self::str($row, $key));
    }

    /** @param array<string, mixed> $row */
    protected static function uuidN(array $row, string $key): ?Uuid
    {
        $v = self::strN($row, $key);

        return $v === null ? null : Uuid::fromString($v);
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            return match (strtolower($value)) {
                't', 'true', '1', 'y', 'yes', 'on'  => true,
                'f', 'false', '0', 'n', 'no', 'off' => false,
                default => throw new RuntimeException(
                    "Cannot convert value to bool: '" . $value . "'"
                ),
            };
        }

        throw new RuntimeException(
            'Cannot convert value to bool: ' . get_debug_type($value)
        );
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
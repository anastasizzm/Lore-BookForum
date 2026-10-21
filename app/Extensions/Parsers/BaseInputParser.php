<?php
declare(strict_types=1);

namespace App\Extensions\Parsers;

use App\Extensions\EnumExtensions;
use App\Models\Uuid;
use InvalidArgumentException;
use UnitEnum;

abstract class BaseInputParser
{
    abstract protected static function fail(string $key, string $message): never;

    // ==================== Optional ====================

    /** @param array<string, mixed> $input */
    public static function optionalString(array $input, string $key): ?string
    {
        if (!array_key_exists($key, $input) || $input[$key] === null) {
            return null;
        }

        $value = $input[$key];

        if (is_string($value) && trim($value) === '') {
            return null;
        }

        return self::parseString($value, $key);
    }

    /** @param array<string, mixed> $input */
    public static function optionalInt(array $input, string $key): ?int
    {
        if (!array_key_exists($key, $input) || $input[$key] === null) {
            return null;
        }

        $value = $input[$key];

        if (is_string($value) && trim($value) === '') {
            return null;
        }

        return self::parseInt($value, $key);
    }

    /** @param array<string, mixed> $input */
    public static function optionalPositiveInt(array $input, string $key): ?int
    {
        $value = self::optionalInt($input, $key);

        if ($value === null) {
            return null;
        }

        if ($value <= 0) {
            static::fail($key, "'{$key}' must be a positive integer");
        }

        return $value;
    }

    /** @param array<string, mixed> $input */
    public static function optionalBool(array $input, string $key): ?bool
    {
        if (!array_key_exists($key, $input) || $input[$key] === null) {
            return null;
        }

        $value = $input[$key];

        if (is_string($value) && trim($value) === '') {
            return null;
        }

        return self::parseBool($value, $key);
    }

    /**
     * @template T of UnitEnum
     * @param array<string, mixed> $input
     * @param class-string<T>      $enumClass
     * @return T|null
     */
    public static function optionalEnum(
        array $input,
        string $key,
        string $enumClass,
    ): ?UnitEnum {
        if (!array_key_exists($key, $input) || $input[$key] === null) {
            return null;
        }

        $value = $input[$key];

        if (is_string($value) && trim($value) === '') {
            return null;
        }

        if (!is_string($value)) {
            static::fail($key, "'{$key}' must be a string");
        }

        $enum = EnumExtensions::tryResolve($enumClass, $value);

        if ($enum === null) {
            static::fail($key, "'{$value}' is not a valid value for '{$key}'");
        }

        return $enum;
    }

    public static function optionalUuid(array $input, string $key): ?Uuid
    {
        $value = self::optionalString($input, $key);

        if ($value === null) {
            return null;
        }

        try {
            return Uuid::fromString($value);
        } catch (InvalidArgumentException) {
            static::fail($key, "'{$key}' must be a valid UUID");
        }
    }

    /**
     * @param  array<string, mixed> $input
     * @return array<mixed>|null
     */
    public static function optionalArray(array $input, string $key): ?array
    {
        if (!array_key_exists($key, $input) || $input[$key] === null) {
            return null;
        }

        return self::parseArray($input[$key], $key);
    }

    /**
     * @param  array<string, mixed> $input
     * @return array<string, mixed>|null
     */
    public static function optionalAssocArray(array $input, string $key): ?array
    {
        $value = self::optionalArray($input, $key);

        if ($value === null) {
            return null;
        }

        if ($value === [] || array_is_list($value)) {
            static::fail($key, "'{$key}' must be a non-empty associative array");
        }

        return $value;
    }

    // ==================== Required ====================

    /** @param array<string, mixed> $input */
    public static function string(array $input, string $key): string
    {
        if (!array_key_exists($key, $input) || $input[$key] === null) {
            static::fail($key, "'{$key}' is required");
        }

        $value = $input[$key];

        if (is_string($value) && trim($value) === '') {
            static::fail($key, "'{$key}' is required");
        }

        return self::parseString($value, $key);
    }

    /** @param array<string, mixed> $input */
    public static function int(array $input, string $key): int
    {
        if (!array_key_exists($key, $input) || $input[$key] === null) {
            static::fail($key, "'{$key}' is required");
        }

        $value = $input[$key];

        if (is_string($value) && trim($value) === '') {
            static::fail($key, "'{$key}' is required");
        }

        return self::parseInt($value, $key);
    }

    /** @param array<string, mixed> $input */
    public static function positiveInt(array $input, string $key): int
    {
        $value = self::int($input, $key);

        if ($value <= 0) {
            static::fail($key, "'{$key}' must be a positive integer");
        }

        return $value;
    }

    /** @param array<string, mixed> $input */
    public static function bool(array $input, string $key): bool
    {
        if (!array_key_exists($key, $input) || $input[$key] === null) {
            static::fail($key, "'{$key}' is required");
        }

        $value = $input[$key];

        if (is_string($value) && trim($value) === '') {
            static::fail($key, "'{$key}' is required");
        }

        return self::parseBool($value, $key);
    }

    /**
     * @template T of UnitEnum
     * @param array<string, mixed> $input
     * @param class-string<T>      $enumClass
     * @return T
     */
    public static function enum(
        array $input,
        string $key,
        string $enumClass,
    ): UnitEnum {
        if (!array_key_exists($key, $input) || $input[$key] === null) {
            static::fail($key, "'{$key}' is required");
        }

        $value = $input[$key];

        if (is_string($value) && trim($value) === '') {
            static::fail($key, "'{$key}' is required");
        }

        if (!is_string($value)) {
            static::fail($key, "'{$key}' must be a string");
        }

        $enum = EnumExtensions::tryResolve($enumClass, $value);

        if ($enum === null) {
            static::fail($key, "'{$value}' is not a valid value for '{$key}'");
        }

        return $enum;
    }

    public static function uuid(array $input, string $key): Uuid
    {
        $value = self::string($input, $key);

        try {
            return Uuid::fromString($value);
        } catch (InvalidArgumentException) {
            static::fail($key, "'{$key}' must be a valid UUID");
        }
    }

    /**
     * @param  array<string, mixed> $input
     * @return array<mixed>
     */
    public static function array(array $input, string $key): array
    {
        if (!array_key_exists($key, $input) || $input[$key] === null) {
            static::fail($key, "'{$key}' is required");
        }

        return self::parseArray($input[$key], $key);
    }

    /**
     * @param  array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function assocArray(array $input, string $key): array
    {
        $value = self::array($input, $key);

        if ($value === [] || array_is_list($value)) {
            static::fail($key, "'{$key}' must be a non-empty associative array");
        }

        return $value;
    }

    // ==================== Internals ====================

    private static function parseString(mixed $value, string $key): string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (!is_string($value)) {
            static::fail($key, "'{$key}' must be a string");
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            static::fail($key, "'{$key}' must be a non-empty string");
        }

        return $trimmed;
    }

    private static function parseInt(mixed $value, string $key): int
    {
        // JSON native: int
        if (is_int($value)) {
            return $value;
        }

        // JSON native: float, но целое (5.0 → 5)
        if (is_float($value) && floor($value) === $value) {
            return (int) $value;
        }

        // Query/formdata/route: строка
        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === '' || !preg_match('/^-?\d+$/', $trimmed)) {
                static::fail($key, "'{$key}' must be an integer");
            }

            return (int) $trimmed;
        }

        static::fail($key, "'{$key}' must be an integer");
    }

    private static function parseBool(mixed $value, string $key): bool
    {
        // JSON native: bool
        if (is_bool($value)) {
            return $value;
        }

        // JSON native: int 0/1
        if (is_int($value)) {
            if ($value === 0) {
                return false;
            }
            if ($value === 1) {
                return true;
            }

            static::fail($key, "'{$key}' must be a boolean");
        }

        // Query/formdata/route: строка
        if (is_string($value)) {
            return match (strtolower(trim($value))) {
                '1', 'true',  'yes', 'on'  => true,
                '0', 'false', 'no',  'off' => false,
                default => static::fail($key, "'{$key}' must be a boolean"),
            };
        }

        static::fail($key, "'{$key}' must be a boolean");
    }

    /** @return array<mixed> */
    private static function parseArray(mixed $value, string $key): array
    {
        if (!is_array($value)) {
            static::fail($key, "'{$key}' must be an array");
        }

        return $value;
    }
}
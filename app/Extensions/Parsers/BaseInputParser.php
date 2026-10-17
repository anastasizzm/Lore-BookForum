<?php
declare(strict_types=1);

namespace App\Extensions\Parsers;

use App\Extensions\EnumExtensions;
use UnitEnum;
use InvalidArgumentException;
use App\Models\Uuid;

abstract class BaseInputParser
{
    /**
     * Вызывается при любой ошибке парсинга.
     * Наследники решают, какое исключение бросать.
     */
    abstract protected static function fail(string $key, string $message): never;

    // ==================== Optional ====================

    /** @param array<string, mixed> $input */
    public static function optionalString(array $input, string $key): ?string
    {
        $value = $input[$key] ?? null;

        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /** @param array<string, mixed> $input */
    public static function optionalInt(array $input, string $key): ?int
    {
        $value = self::optionalString($input, $key);

        if ($value === null) {
            return null;
        }

        if (!preg_match('/^-?\d+$/', $value)) {
            static::fail($key, "'{$key}' must be an integer");
        }

        return (int) $value;
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
        $value = self::optionalString($input, $key);

        if ($value === null) {
            return null;
        }

        return match (strtolower($value)) {
            '1', 'true',  'yes', 'on'  => true,
            '0', 'false', 'no',  'off' => false,
            default => static::fail($key, "'{$key}' must be a boolean"),
        };
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
        $value = self::optionalString($input, $key);

        if ($value === null) {
            return null;
        }

        $enum = EnumExtensions::tryResolve($enumClass, $value);

        if ($enum === null) {
            static::fail($key, "'{$value}' is not a valid value for '{$key}'");
        }

        return $enum;
    }

    // ==================== Required ====================

    /** @param array<string, mixed> $input */
    public static function string(array $input, string $key): string
    {
        if (!array_key_exists($key, $input)) {
            static::fail($key, "'{$key}' is required");
        }

        $value = $input[$key];

        if (!is_string($value) || trim($value) === '') {
            static::fail($key, "'{$key}' must be a non-empty string");
        }

        return trim($value);
    }

    /** @param array<string, mixed> $input */
    public static function int(array $input, string $key): int
    {
        $value = self::string($input, $key);

        if (!preg_match('/^-?\d+$/', $value)) {
            static::fail($key, "'{$key}' must be an integer");
        }

        return (int) $value;
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
        $value = self::string($input, $key);

        return match (strtolower($value)) {
            '1', 'true',  'yes', 'on'  => true,
            '0', 'false', 'no',  'off' => false,
            default => static::fail($key, "'{$key}' must be a boolean"),
        };
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
        $value = self::string($input, $key);

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
     * @return array<mixed>|null
     */
    public static function optionalArray(array $input, string $key): ?array
    {
        $value = $input[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            static::fail($key, "'{$key}' must be an array");
        }

        return $value;
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

        if (!array_is_list($value) === false && $value !== []) {
            // array_is_list возвращает true для списка — нам нужен ассоциативный
            static::fail($key, "'{$key}' must be an associative array");
        }

        return $value;
    }

    // ==================== Required ====================

    /**
     * @param  array<string, mixed> $input
     * @return array<mixed>
     */
    public static function array(array $input, string $key): array
    {
        if (!array_key_exists($key, $input)) {
            static::fail($key, "'{$key}' is required");
        }

        $value = $input[$key];

        if (!is_array($value)) {
            static::fail($key, "'{$key}' must be an array");
        }

        return $value;
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
}
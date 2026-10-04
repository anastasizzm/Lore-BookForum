<?php
declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;

final readonly class Uuid
{
    private function __construct(public string $value) {}

    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));

        if (!preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $normalized,
        )) {
            throw new InvalidArgumentException("Invalid UUID: $value");
        }

        return new self($normalized);
    }

    public static function generate(): self
    {
        // v4 UUID из random_bytes
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);  // version 4
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);  // variant 1

        return new self(vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4)));
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
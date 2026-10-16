<?php
declare(strict_types=1);

namespace App\Models\Access\Rules;

readonly class PublicationAccessRules 
{
    public function __construct(
        public bool $manage,
        public bool $read,
        public bool $managePeople
    ){}

    public function hasAny() : bool 
    {
        return $this->manage || $this->read || $this->managePeople;
    }

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        return new self(
            manage: self::bool($row, $prefix . 'manage'),
            read: self::bool($row, $prefix . 'read'),
            managePeople: self::bool($row, $prefix . 'manage_ppl'),
        );
    }
}
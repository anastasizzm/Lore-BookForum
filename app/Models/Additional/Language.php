<?php
declare(strict_types=1);

namespace App\Models\Additional;

use App\Models\Dto;
use DateTimeImmutable;

final readonly class Language extends Dto
{
    public function __construct(
        public string $code,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $deletedAt
    ){}

    public function id() : mixed { return $this->code; }

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        return new self(
            code: self::str($row, $prefix . 'code'),
            createdAt: self::dt($row, $prefix . 'created_at'),
            deletedAt: self::dtN($row, $prefix . 'deleted_at'),
        );
    }

    public function isActive() : bool { return $this->deletedAt !== NULL; }
}
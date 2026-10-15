<?php
declare(strict_types=1);

namespace App\Models\Access\Assignment;
use DateTimeImmutable;
use App\Models\Dto;

readonly class PermissionsUsers extends Dto
{
    public function __construct(
        public int $id,
        public int $userId,
        public int $roleId,
        public DateTimeImmutable $validFrom,
        public ?DateTimeImmutable $validTo,
        public bool $isValid,
        public int $grantedById
    ){}

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        return new self(
            id: self::int($row, $prefix . 'id'),
            userId: self::int($row, $prefix . 'user_id'),
            roleId: self::int($row, $prefix . 'role_id'),
            validFrom: self::dt($row, $prefix . 'valid_from'),
            validTo: self::dtN($row, $prefix . 'valid_to'),
            isValid: self::bool($row, $prefix . 'is_valid'),
            grantedById: self::int($row, $prefix . 'granted_by_id')
        );
    }
}
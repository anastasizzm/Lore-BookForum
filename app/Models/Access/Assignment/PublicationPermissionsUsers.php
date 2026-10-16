<?php
declare(strict_types=1);

namespace App\Models\Access\Assignment;

readonly class PublicationPermissionsUsers extends PermissionsUsers 
{
    public function __construct(
        public int $publicationId,
        int $id,
        int $userId,
        int $roleId,
        DateTimeImmutable $validFrom,
        ?DateTimeImmutable $validTo,
        bool $isValid,
        int $grantedById
    ){
        parent::__construct($id, $userId, $roleId, $validFrom, $validTo, $isValid, $grantedById);
    }

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        $parent = parent::fromRow($row, $prefix);
        return new self(
            publicationId: self::int($row, $prefix . 'publication_id'),
            id: $parent->id,
            userId: $parent->userId,
            roleId: $parent->roleId,
            validFrom: $parent->validFrom,
            validTo: $parent->validTo,
            isValid: $parent->isValid,
            grantedById: $parent->grantedById
        );
    }
}
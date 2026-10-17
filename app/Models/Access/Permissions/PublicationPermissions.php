<?php
declare(strict_types=1);

namespace App\Models\Access\Permissions;
use App\Models\Access\Rules\PublicationAccessRules;
use App\Models\Dto;

readonly class PublicationPermissions extends Dto
{
    public function __construct(
        public int $publicationId,
        public int $roleId,
        public PublicationAccessRules $rules,
    ){}

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        return new self(
            publicationId: self::int($row, $prefix . 'publication_id'),
            roleId: self::int($row, $prefix . 'role_id'),
            rules: PublicationAccessRules::fromRow($row, $prefix)
        );
    }
}
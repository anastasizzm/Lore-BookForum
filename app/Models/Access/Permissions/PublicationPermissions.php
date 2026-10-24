<?php
declare(strict_types=1);

namespace App\Models\Access\Permissions;
use App\Models\Access\Rules\PublicationAccessRules;
use App\Models\Dto;

readonly class PublicationPermissions extends Dto
{
    public function __construct(
        public int $roleId,
        public PublicationAccessRules $pubPermissions,
    ){}

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        return new self(
            roleId: self::int($row, $prefix . 'role_id'),
            pubPermissions: PublicationAccessRules::fromRow($row, $prefix)
        );
    }
}
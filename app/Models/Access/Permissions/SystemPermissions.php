<?php
declare(strict_types=1);

namespace App\Models\Access\Permissions;
use App\Models\Access\Rules\SystemAccessRules;
use App\Models\Dto;


readonly class SystemPermissions extends Dto
{
    public function __construct(
        public int $roleId,
        public SystemAccessRules $rules
    ){}

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        return new self(
            roleId: self::int($row, $prefix . 'role_id'),
            rules: SystemAccessRules::fromRow($row, $prefix)
        );
    }
}
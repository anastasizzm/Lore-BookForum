<?php
declare(strict_types=1);

namespace App\Models\Access\Permissions;
use App\Models\Access\Rules\SystemAccessRules;
use App\Models\Access\Rules\PostAccessRules;
use App\Models\Access\Rules\PublicationAccessRules;
use App\Models\Dto;

readonly class SystemPermissions extends Dto
{
    public function __construct(
        public ?UserRole $role,
        private int $roleId,
        public SystemAccessRules $systemPermissions,
        public PostAccessRules $postPermissions,
        public PublicationAccessRules $pubPermissions
    ){}

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        $r = 'r_';
        return new self(
            roleId: self::int($row, $prefix . 'role_id'),
            role: self::hasGroup($row, $r)
                ? UserRole::fromRow($row, $prefix . $r) : NULL,
            systemPermissions: SystemAccessRules::fromRow($row, $prefix . 'sys_'),
            postPermissions: PostAccessRules::fromRow($row, $prefix . 'post_'),
            pubPermissions: PublicationAccessRules::fromRow($row, $prefix . 'pub_'),
        );
    }

    public function getRoleId() : int { return $this->roleId; }
}
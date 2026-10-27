<?php
declare(strict_types=1);

namespace App\Models\Access\Permissions;
use App\Models\Access\Rules\PublicationAccessRules;
use App\Models\Additional\UserRole;
use App\Models\Dto;

readonly class PublicationPermissions extends Dto
{
    public function __construct(
        public ?UserRole $role,
        private int $roleId,
        public PublicationAccessRules $pubPermissions,
    ){}

    public function id() : mixed { return $this->roleId; }

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        $r = 'r_';
        return new self(
            roleId: self::int($row, $prefix . 'role_id'),
            role: self::hasGroup($row, $r, 'id')
                ? UserRole::fromRow($row, $prefix . $r) : NULL,
            pubPermissions: PublicationAccessRules::fromRow($row, $prefix . 'pub_')
        );
    }

    public function getRoleId() : int { return $this->roleId; }

    public function toArray() : array 
    {
        return [
            'role' => $this->role?->toArray(),
            'pubPermissions' => $this->pubPermissions->toArray(),
        ];
    }
}
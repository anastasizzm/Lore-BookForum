<?php
declare(strict_types=1);

namespace App\Models\UserContext;

final readonly class VersionedPermissionsContext extends PermissionsContext
{
    public function __construct(
        public bool $canManage,
        public int $version
    ) {}

    public function toArray(): array
    {
        return [
            'canManage' => $this->canManage,
            'version' => $this->version
        ];
    }

    public static function fromEmpty() : self 
    {
        return new self(
            canManage: false,
            version: 1
        );
    }
}
<?php
declare(strict_types=1);

namespace App\Models\UserContext;

final readonly class PermissionsContext implements UserContextInterface
{
    public function __construct(
        public bool $canManage,
    ) {}

    public function toArray(): array
    {
        return [
            'canManage' => $this->canManage,
        ];
    }

    public static function fromEmpty() : self 
    {
        return new self(
            canManage: false,
        );
    }
}
<?php
declare(strict_types=1);

namespace App\Models\UserContext;

interface UserContextInterface
{
    /** @return array<string, bool> */
    public function toArray(): array;
}
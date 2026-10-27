<?php
declare(strict_types=1);

namespace App\Models\Access\Rules;

interface HasRolesManage
{
    public function hasManagePeople() : bool;
    public function hasManageRoles() : bool;
}
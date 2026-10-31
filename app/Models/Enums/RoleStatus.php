<?php
declare(strict_types=1);

namespace App\Models\Enums;

enum RoleStatus : string 
{
    case Public = 'public';
    case Private = 'private';
    case All = 'all';
}
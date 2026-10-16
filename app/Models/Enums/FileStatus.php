<?php
declare(strict_types=1);

namespace App\Models\Enums;

enum FileStatus : string 
{
    case Active = 'active';
    case Pending = 'pending';
}
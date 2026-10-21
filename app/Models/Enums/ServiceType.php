<?php
declare(strict_types=1);

namespace App\Models\Enums;

enum ServiceType : string
{
    case Publication = 'publication';
    case Post = 'post';
    case System = 'system';
    case Account = 'account';
}
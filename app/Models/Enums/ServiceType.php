<?php
declare(strict_types=1);

namespace App\Models\Enums;

enum ServiceType 
{
    case Publication;
    case Post;
    case System;
    case Account;
}
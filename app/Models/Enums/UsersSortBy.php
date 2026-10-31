<?php
declare(strict_types=1);

namespace App\Models\Enums;

enum UsersSortBy : string
{
    case Username = 'username';
    case Name = 'name';
    case Created = 'created';
}
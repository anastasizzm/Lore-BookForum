<?php
declare(strict_types=1);

namespace App\Models\Enums;

enum FileCategory : string
{
    case Cover = 'cover';
    case Book = 'book';
}
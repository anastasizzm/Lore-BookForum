<?php
declare(strict_types=1);

namespace App\Models\Enums;

enum BasicModelSortBy : string
{
    case Alphabet = 'alpha';
    case Newest = 'newest';
}
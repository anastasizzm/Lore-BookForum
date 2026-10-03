<?php
declare(strict_types=1);

namespace App\Models\Enums;

enum PostsSortBy : string
{
    case Popularity = 'populatiry';
    case Newest = 'newest';
}
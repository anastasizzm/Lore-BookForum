<?php
declare(strict_types=1);

namespace App\Models\Enums;

enum PublicationsSortBy : string
{
    case Popularity = 'popularity';
    case Newest = 'newest';
    case Alphabet = 'alpha';
}
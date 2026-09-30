<?php
declare(strict_types=1);

namespace App\Models\Enums;

enum ArticleType : string
{
    case Book = 'book';
    case Content = 'content';
}
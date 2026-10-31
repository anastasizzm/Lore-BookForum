<?php
declare(strict_types=1);

namespace App\Models\Enums;

enum ArticleContentMode : string
{
    case Book = 'book';
    case Text = 'text';
}
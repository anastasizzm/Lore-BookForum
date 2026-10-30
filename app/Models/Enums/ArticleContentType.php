<?php
declare(strict_types=1);

namespace App\Models\Enums;

enum ArticleContentType
{
    case None;
    case Book;
    case Content;
}
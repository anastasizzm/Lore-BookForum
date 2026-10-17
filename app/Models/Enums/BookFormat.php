<?php
declare(strict_types=1);

namespace App\Models\Enums;

enum BookFormat: string
{
    case Pdf  = 'pdf';
    case Epub = 'epub';
    case Mobi = 'mobi';
    case Fb2  = 'fb2';
    case Azw  = 'azw';
}
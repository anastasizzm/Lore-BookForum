<?php
declare(strict_types=1);

namespace App\Models\Enums;

enum ReadingStatus : string
{
    case None = 'none';
    case Reading = 'reading';
    case Ended = 'ended';
}
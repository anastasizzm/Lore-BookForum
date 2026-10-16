<?php
declare(strict_types=1);

namespace App\Models\Enums;

enum PublicationStatus : string 
{
    case Public = 'public';
    case Draft = 'draft';
}
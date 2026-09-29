<?php
declare(strict_types=1);

namespace App\Queries;

interface Query
{
    public static function fromInput(array $query) : self;
}
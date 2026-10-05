<?php
declare(strict_types=1);

namespace App\Models\Queries;

interface Query
{
    public static function fromInput(array $query) : self;

    public function hasData() : bool;

    public static function default() : self;
}
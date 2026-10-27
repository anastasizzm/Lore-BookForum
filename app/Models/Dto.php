<?php
declare(strict_types=1);

namespace App\Models;

abstract readonly class Dto extends RowModel
{
    public abstract function id() : mixed;
}
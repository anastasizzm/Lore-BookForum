<?php
declare(strict_types=1);

namespace App\Services;

interface UnitOfWork
{
    public function transactional(callable $fn) : mixed;
}
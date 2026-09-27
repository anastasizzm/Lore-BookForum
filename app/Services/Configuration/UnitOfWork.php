<?php
declare(strict_types=1);

namespace App\Services\Configuration;

interface UnitOfWork
{
    public function transactional(callable $fn) : mixed;
}
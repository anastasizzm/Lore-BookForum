<?php
declare(strict_types=1);

namespace App\Forms;

interface Form
{
    /** @param array<string, mixed> $input*/
    public static function fromInput(array $form) : self;
}
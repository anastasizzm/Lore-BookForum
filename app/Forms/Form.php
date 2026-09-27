<?php
declare(strict_types=1);

namespace App\Forms;

interface Form
{
    /** @param array<string, mixed> $input*/
    public static function fromArray(array $form) : self;

    /** @return array<string, list<string>> */
    public function validate() : array;
}
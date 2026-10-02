<?php
declare(strict_types=1);

namespace App\Models\Scripts;

use PDO;

final readonly class ScriptParam
{
    public function __construct(
        private mixed $value,
        private int $type = PDO::PARAM_STR
    ){}

    public function getValue() { return $this->value; }
    public function getType() : int { return $this->type; }

    public static function asStr(mixed $value) : self { return new self($value, PDO::PARAM_STR); }
    public static function asInt(mixed $value) : self { return new self($value, PDO::PARAM_INT); }
    public static function asBool(mixed $value) : self { return new self($value, PDO::PARAM_BOOL); }
    public static function asNull(mixed $value) : self { return new self($value, PDO::PARAM_NULL); }
}
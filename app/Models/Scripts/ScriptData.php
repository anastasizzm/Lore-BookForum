<?php
declare(strict_types=1);

namespace App\Models\Scripts;

final readonly class ScriptData
{
    public function __construct(
        private string $script,
        private array $params
    ) {}

    public function getScript() : string { return $this->script; }
    public function getParams() : array { return $this->params; }
}
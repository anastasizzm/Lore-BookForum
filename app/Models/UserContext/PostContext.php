<?php
declare(strict_types=1);

namespace App\Models\UserContext;
use App\Models\Dto;

final readonly class PostContext extends Dto implements UserContextInterface
{
    public function __construct(
        public bool $isLiked = false,
        public bool $isEditor = false
    ) {}

    public function toArray(): array
    {
        return [
            'isLiked' => $this->isLiked,
            'isEditor' => $this->isEditor
        ];
    }

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        return new self(
            isLiked: self::bool($row, $prefix . 'is_liked'),
            isEditor: self::bool($row, $prefix . 'is_editor'),
        );
    }
}
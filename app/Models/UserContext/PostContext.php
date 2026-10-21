<?php
declare(strict_types=1);

namespace App\Models\UserContext;
use App\Models\Dto;

final readonly class PostContext extends Dto implements UserContextInterface
{
    public function __construct(
        public bool $isLiked = false
    ) {}

    public function toArray(): array
    {
        return [
            'isLiked' => $this->isLiked
        ];
    }

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        return new self(
            isLiked: self::bool($row, $prefix . 'is_liked')
        );
    }
}
<?php
declare(strict_types=1);

namespace App\Models\UserContext;
use App\Models\Dto;
use App\Models\Access\Rules\PostAccessRules;

final readonly class PostContext extends Dto implements UserContextInterface
{
    public function __construct(
        public bool $isLiked,
        public PostAccessRules $rules
    ) {}

    public function toArray(): array
    {
        return [
            'isLiked' => $this->isLiked,
            'rules' => $this->rules->toArray()
        ];
    }

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        return new self(
            isLiked: self::bool($row, $prefix . 'is_liked'),
            rules: PostAccessRules::fromEmpty()
        );
    }

    public static function fromEmpty() : self 
    {
        return new self(
            isLiked: false,
            rules: PostAccessRules::fromEmpty()
        );
    }

    public function getRules() { return $this->rules; }
    public function withRules(PostAccessRules $rules) : self 
    {
        return new self(
            isLiked: $this->isLiked,
            rules: $rules
        );
    }
}
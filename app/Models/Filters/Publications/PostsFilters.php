<?php
declare(strict_types=1);

namespace App\Models\Filters\Publications;

use App\Extensions\Parsers\QueryParser;

final readonly class PostsFilters
{
    public function __construct(
        public ?string $search = null,
        public ?int $creatorId = null,
        public ?int $parentId = null,
        public ?int $publicationId = null
    ) {}

    /** @param array<string, mixed> $q */
    public static function fromInput(array $q): self
    {
        return new self(
            search: QueryParser::optionalString($q, 'q'),
            creatorId: QueryParser::optionalPositiveInt($q, 'creator'),
            parentId: QueryParser::optionalPositiveInt($q, 'parent'),
            publicationId: QueryParser::optionalPositiveInt($q, 'publication'),
        );
    }
}
<?php
declare(strict_types=1);

namespace App\Models\Filters\Publications;

use App\Extensions\Parsers\QueryParser;

final readonly class BooksFilters extends PublicationsFilters
{
    public function __construct(
        ?int $genreId = null,
        ?int $creatorId = null,
        ?string $search = null,
        public ?string $isbn = null,
    ) {
        parent::__construct($genreId, $creatorId, $search);
    }

    /** @param array<string, mixed> $q */
    public static function fromInput(array $q): self
    {
        $parent = parent::fromInput($q);
        return new self(
            genreId: $parent->genreId,
            creatorId: $parent->creatorId,
            search: $parent->search,
            isbn: QueryParser::optionalString($q, 'isbn'),
        );
    }
}
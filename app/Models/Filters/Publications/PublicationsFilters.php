<?php
declare(strict_types=1);

namespace App\Models\Filters\Publications;

use App\Extensions\Parsers\QueryParser;

readonly class PublicationsFilters
{
    public function __construct(
        public ?int $genreId = null,
        public ?int $creatorId = null,
        public ?string $search = null,
    ) {}

    /** Всё из HTTP. Удобно, когда override не нужен. */
    /** @param array<string, mixed> $q */
    public static function fromInput(array $q): self
    {
        return new self(
            genreId: QueryParser::optionalPositiveInt($q, 'genre'),
            creatorId: QueryParser::optionalPositiveInt($q, 'creator'),
            search: QueryParser::optionalString($q, 'q'),
        );
    }

    public static function fromCreator(array $q, int $creatorId) : self 
    {
        return new self(
            creatorId: $creatorId,
            genreId: QueryParser::optionalPositiveInt($q, 'genre'),
            search: QueryParser::optionalString($q, 'q'),
        );
    }
}
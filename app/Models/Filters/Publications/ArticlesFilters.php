<?php
declare(strict_types=1);

namespace App\Models\Filters\Publications;

use App\Extensions\Parsers\QueryParser;

final readonly class ArticlesFilters extends PublicationsFilters
{
    public function __construct(
        ?int $genreId = null,
        ?int $creatorId = null,
        ?string $search = null,
        public ?int $typeId = null,
        public ?int $bookId = null,
        public ?string $doi = null,
    ) {
        parent::__construct($genreId, $creatorId, $search);
    }

    /** @param array<string, mixed> $q */
    public static function fromInput(array $q): self
    {
        $parent = parent::fromInput($q);
        return self::fromParent($q, $parent);
    }

    public static function fromCreator(array $q, int $creatorId) : self
    {
        $parent = parent::fromCreator($q, $creatorId);
        return self::fromParent($q, $parent);
    }

    private static function fromParent(array $q, parent $parent) : self 
    {
        return new self(
            genreId: $parent->genreId,
            creatorId: $parent->creatorId,
            search: $parent->search,
            typeId: QueryParser::optionalPositiveInt($q, 'type'),
            bookId: QueryParser::optionalPositiveInt($q, 'book'),
            doi: QueryParser::optionalString($q, 'doi'),
        );
    }
}
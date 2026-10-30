<?php
declare(strict_types=1);

namespace App\Models\Filters\Publications;

use App\Extensions\Parsers\QueryParser;
use App\Models\Enums\PublicationStatus;

final readonly class BooksFilters extends PublicationsFilters
{
    public function __construct(
        ?int $genreId = null,
        ?int $creatorId = null,
        ?string $search = null,
        PublicationStatus $status = PublicationStatus::Public,
        public ?int $categoryId = null,
        public ?string $isbn = null,
    ) {
        parent::__construct($genreId, $creatorId, $search, $status);
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

    public static function fromDraft(array $q) : self
    {
        $parent = parent::fromDraft($q);
        return self::fromParent($q, $parent);
    }

    public static function fromPublic(array $q) : self
    {
        $parent = parent::fromPublic($q);
        return self::fromParent($q, $parent);
    }

    private static function fromParent(array $q, parent $parent) : self 
    {
        return new self(
            genreId: $parent->genreId,
            creatorId: $parent->creatorId,
            search: $parent->search,
            status: $parent->status,
            categoryId: QueryParser::optionalPositiveInt($q, 'category'),
            isbn: QueryParser::optionalString($q, 'isbn'),
        );
    }
}
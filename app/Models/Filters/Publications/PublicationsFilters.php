<?php
declare(strict_types=1);

namespace App\Models\Filters\Publications;

use App\Extensions\Parsers\QueryParser;
use App\Models\Enums\PublicationStatus;

readonly class PublicationsFilters
{
    public function __construct(
        public ?int $genreId = null,
        public ?int $creatorId = null,
        public ?string $search = null,
        public PublicationStatus $status = PublicationStatus::Public
    ) {}

    /** @param array<string, mixed> $q */
    public static function fromInput(array $q): self
    {
        return new self(
            genreId: QueryParser::optionalPositiveInt($q, 'genre'),
            creatorId: QueryParser::optionalPositiveInt($q, 'creator'),
            search: QueryParser::optionalString($q, 'q'),
            status: QueryParser::optionalEnum($q, 'p_status', PublicationStatus::class) ?? PublicationStatus::Public
        );
    }

    public static function fromDraft(array $q) : self
    {
        return new self(
            genreId: QueryParser::optionalPositiveInt($q, 'genre'),
            creatorId: QueryParser::optionalPositiveInt($q, 'creator'),
            search: QueryParser::optionalString($q, 'q'),
            status: PublicationStatus::Draft
        );
    }

    public static function fromPublic(array $q) : self
    {
        return new self(
            genreId: QueryParser::optionalPositiveInt($q, 'genre'),
            creatorId: QueryParser::optionalPositiveInt($q, 'creator'),
            search: QueryParser::optionalString($q, 'q'),
            status: PublicationStatus::Public
        );
    }

    public static function fromCreator(array $q, int $creatorId) : self 
    {
        return new self(
            creatorId: $creatorId,
            genreId: QueryParser::optionalPositiveInt($q, 'genre'),
            search: QueryParser::optionalString($q, 'q'),
            status: QueryParser::optionalEnum($q, 'p_status', PublicationStatus::class) ?? PublicationStatus::Public
        );
    }
}
<?php
declare(strict_types=1);

namespace App\Models\Filters\Publications;

use App\Models\Queries\StatusQuery;
use App\Extensions\Parsers\QueryParser;

final readonly class UserRelationFilters
{
    public StatusQuery $status;

    public function __construct(
        public int $viewerId,
        ?StatusQuery $status = NULL,
        public bool $savedOnly = false,
    ) {
        $this->status = $status ?? StatusQuery::default();
    }

    /** @param array<string, mixed> $q */
    public static function fromInput(array $q, int $viewerId): self
    {
        return new self(
            viewerId:  $viewerId,
            status: StatusQuery::fromInput($q),
            savedOnly: QueryParser::optionalBool($q, 'saved') ?? false,
        );
    }

    public static function fromAll(array $q, int $viewerId) : self
    {
        return new self(
            viewerId:  $viewerId,
            status: StatusQuery::fromInput($q),
            savedOnly: false
        );
    }

    public static function fromSaved(array $q, int $viewerId) : self
    {
        return new self(
            viewerId:  $viewerId,
            status: StatusQuery::fromInput($q),
            savedOnly: true
        );
    }

    public function isEmpty(): bool
    {
        return !$this->status->hasData() && !$this->savedOnly;
    }
}
<?php
declare(strict_types=1);

namespace App\Forms\Publications;

use App\Forms\Form;
use App\Extensions\Parsers\QueryParser;
use App\Models\Uuid;

readonly class PublicationPatchForm implements Form
{
    public function __construct(
        public ?string $title,
        public ?string $description,
        public ?string $authorNotes,
        public ?int $genreId,
        public ?Uuid $iconId,
        
        public bool $hasTitle,
        public bool $hasDescription,
        public bool $hasAuthorNotes,
        public bool $hasGenreId,
        public bool $hasIconId
    ){}

    public static function fromInput(array $input) : self
    {
        return new self(
            title: QueryParser::optionalString($input, 'title'),
            description: QueryParser::optionalString($input, 'description'),
            authorNotes: QueryParser::optionalString($input, 'authorNotes'),
            genreId: QueryParser::optionalPositiveInt($input, 'genreId'),
            iconId: QueryParser::optionalUuid($input, 'iconId'),
            hasTitle: array_key_exists('title', $input),
            hasDescription: array_key_exists('description', $input),
            hasAuthorNotes: array_key_exists('authorNotes', $input),
            hasGenreId: array_key_exists('genreId', $input),
            hasIconId: array_key_exists('iconId', $input)
        );
    }
}
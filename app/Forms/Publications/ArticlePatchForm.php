<?php
declare(strict_types=1);

namespace App\Forms\Publications;

use App\Extensions\Parsers\QueryParser;
use App\Models\Uuid;

final readonly class ArticlePatchForm extends PublicationPatchForm
{
    public function __construct(
        ?string $title,
        ?string $description,
        ?string $authorNotes,
        ?int $genreId,
        ?Uuid $iconId,
        
        public ?int $typeId,
        public ?string $doi,
        
        bool $hasTitle,
        bool $hasDescription,
        bool $hasAuthorNotes,
        bool $hasGenreId,
        bool $hasIconId,

        public bool $hasTypeId,
        public bool $hasDoi
    ){
        parent::__construct(
            title: $title,
            description: $description,
            authorNotes: $authorNotes,
            genreId: $genreId,
            iconId: $iconId,
            hasTitle: $hasTitle,
            hasDescription: $hasDescription,
            hasAuthorNotes: $hasAuthorNotes,
            hasGenreId: $hasGenreId,
            hasIconId: $hasIconId
        );
    }

    public static function fromInput(array $input) : self
    {
        $parent = parent::fromInput($input);
        
        return new self(
            title: $parent->title,
            description: $parent->description,
            authorNotes: $parent->authorNotes,
            genreId: $parent->genreId,
            iconId: $parent->iconId,
            hasTitle: $parent->hasTitle,
            hasDescription: $parent->hasDescription,
            hasAuthorNotes: $parent->hasAuthorNotes,
            hasGenreId: $parent->hasGenreId,
            hasIconId: $parent->hasIconId,

            typeId: QueryParser::optionalPositiveInt($input, 'typeId'),
            doi: QueryParser::optionalString($input, 'doi'),

            hasTypeId: array_key_exists('typeId', $input),
            hasDoi: array_key_exists('doi', $input)
        );
    }
}
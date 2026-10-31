<?php
declare(strict_types=1);

namespace App\Forms\Publications;

use App\Extensions\Parsers\QueryParser;
use App\Models\Uuid;

final readonly class BookPatchForm extends PublicationPatchForm
{
    public function __construct(
        ?string $title,
        ?string $description,
        ?string $authorNotes,
        ?int $genreId,
        ?Uuid $iconId,
        
        public ?int $categoryId,
        public ?string $publisher,
        public ?int $pages,
        public ?string $isbn,
        
        bool $hasTitle,
        bool $hasDescription,
        bool $hasAuthorNotes,
        bool $hasGenreId,
        bool $hasIconId,

        public bool $hasCategoryId,
        public bool $hasPublisher,
        public bool $hasPages,
        public bool $hasIsbn
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

            categoryId: QueryParser::optionalPositiveInt($input, 'categoryId'),
            publisher: QueryParser::optionalString($input, 'publisher'),
            pages: QueryParser::optionalPositiveInt($input, 'pages'),
            isbn: QueryParser::optionalString($input, 'isbn'),

            hasCategoryId: array_key_exists('categoryId', $input),
            hasPublisher: array_key_exists('publisher', $input),
            hasPages: array_key_exists('pages', $input),
            hasIsbn: array_key_exists('isbn', $input)
        );
    }
}
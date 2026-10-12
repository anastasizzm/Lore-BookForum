<?php
declare(strict_types=1);

namespace App\Forms\Publications;

use App\Forms\Publications\PublicationForm;

final readonly class BookForm extends PublicationForm
{
    public function __construct(
        string $title,
        string $description,
        string $authorNotes,
        int $genreId,
        string $iconId,
        public int $categoryId,
        public string $publisher,
        public int $pages,
        public ?string $isbn
    ){}

    public static function fromInput(array $input) : self
    {
        $parent = parent::fromInput($input);
        return new self(
            title: $parent->title,
            description: $parent->description,
            authorNotes: $parent->authorNotes,
            genreId: $parent->genreId,
            iconId: $parent->iconId,
            categoryId: (int)($input['categoryId'] ?? 0),
            publisher: $input['publisher'] ?? '',
            pages: (int)($input['pages'] ?? 0),
            isbn: $input['isbn']
        );
    }
}
<?php
declare(strict_types=1);

namespace App\Forms\Publications;

use App\Forms\Form;

final readonly class PublicationForm implements Form
{
    public function __construct(
        public string $title,
        public string $description,
        public string $authorNotes,
        public int $genreId,
        public string $iconId
    ){}

    public static function fromInput(array $input) : self
    {
        return new self(
            title: $input['title'] ?? '',
            description: $input['description'] ?? 'No description',
            authorNotes: $input['authorNotes'] ?? '',
            genreId: (int)($input['genreId'] ?? 0),
            iconId: $input['iconId'] ?? ''
        );
    }
}
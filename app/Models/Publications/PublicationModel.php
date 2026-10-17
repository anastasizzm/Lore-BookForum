<?php
declare(strict_types=1);

namespace App\Models\Publications;

use App\Models\Uuid;
use App\Forms\Publications\PublicationForm;

readonly class PublicationModel
{
    public function __construct(
        public string $title,
        public string $description,
        public string $authorNotes,
        public int $genreId,
        public ?Uuid $iconId
    ){}

    public static function fromForm(PublicationForm $form) : self 
    {
        return new self(
            title: $form->title,
            description: $form->description,
            authorNotes: $form->authorNotes,
            genreId: $form->genreId,
            iconId: $form->iconId
        );
    }
}
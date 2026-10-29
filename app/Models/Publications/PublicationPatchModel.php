<?php
declare(strict_types=1);

namespace App\Models\Publications;

use App\Models\Uuid;
use App\Forms\Publications\PublicationPatchForm;

readonly class PublicationPatchModel
{
    public function __construct(
        public string|false $title,
        public string|false $description,
        public string|false $authorNotes,
        public int|false $genreId,
        public Uuid|null|false $iconId,
    ) {}

    public static function fromForm(PublicationPatchForm $form): self
    {
        return new self(
            title:       $form->hasTitle ? (string)$form->title : false,
            description: $form->hasDescription ? (string)$form->description : false,
            authorNotes: $form->hasAuthorNotes ? (string)$form->authorNotes : false,
            genreId:     $form->hasGenreId ? (int)$form->genreId : false,
            iconId:      $form->hasIconId ? $form->iconId : false,
        );
    }
}
<?php
declare(strict_types=1);

namespace App\Models\Publications;

use App\Forms\Publications\BookPatchForm;
use App\Forms\Publications\PublicationPatchForm;
use RuntimeException;

final readonly class BookPatchModel extends PublicationPatchModel
{
    public function __construct(
        string|false $title,
        string|false $description,
        string|false $authorNotes,
        int|false $genreId,
        Uuid|null|false $iconId,
        
        public int|false $categoryId,
        public string|false $publisher,
        public int|false $pages,
        public string|false $isbn
    ){
        parent::__construct($title, $description, $authorNotes, $genreId, $iconId);
    }

    public static function fromForm(PublicationPatchForm $form): self 
    {
        if (!$form instanceof BookPatchForm) {
            throw new RuntimeException("BookPatchForm instance is required");
        }

        $parent = parent::fromForm($form);
        
        return new self(
            title:       $parent->title,
            description: $parent->description,
            authorNotes: $parent->authorNotes,
            genreId:     $parent->genreId,
            iconId:      $parent->iconId,
            
            categoryId:  $form->hasCategoryId ? (int)$form->categoryId : false,
            publisher:   $form->hasPublisher ? (string)$form->publisher : false,
            pages:       $form->hasPages ? (int)$form->pages : false,
            
            isbn:        $form->hasIsbn ? $form->isbn : false,
        );
    }
}
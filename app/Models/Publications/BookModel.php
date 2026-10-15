<?php
declare(strict_types=1);

namespace App\Models\Publications;

use App\Models\Uuid;
use App\Forms\Publications\BookForm;
use App\Forms\Publications\PublicationForm;
use RuntimeException;

final readonly class BookModel extends PublicationModel
{
    public function __construct(
        string $title,
        string $description,
        string $authorNotes,
        int $genreId,
        ?Uuid $iconId,
        public int $categoryId,
        public string $publisher,
        public int $pages,
        public ?string $isbn,
        public array $contentIds
    ){
        parent::__construct($title, $description, $authorNotes, $genreId, $iconId);
    }

    public static function fromForm(PublicationForm $form) : self 
    {
        if (!$form instanceof BookForm)
            throw new RuntimeException("BookForm instance is required");

        $parent = parent::fromForm($form);
        return new self(
            title: $parent->title,
            description: $parent->description,
            authorNotes: $parent->authorNotes,
            genreId: $parent->genreId,
            iconId: $parent->iconId,
            categoryId: $form->categoryId,
            publisher: $form->publisher,
            pages: $form->pages,
            isbn: $form->isbn,
            contentIds: $form->contentIds
        );
    }
}
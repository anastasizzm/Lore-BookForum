<?php
declare(strict_types=1);

namespace App\Models\Publications;

use App\Models\Uuid;
use App\Forms\Publications\ArticleForm;
use App\Forms\Publications\PublicationForm;
use App\Models\Publications\ContentData;
use App\Models\Publications\BookData;
use App\Models\Enums\ArticleContentType;
use RuntimeException;

final readonly class ArticleModel extends PublicationModel
{
    public function __construct(
        string $title,
        string $description,
        string $authorNotes,
        int $genreId,
        ?Uuid $iconId,
        public int $typeId,
        public ContentData $content,
        public ?string $doi,
    ){
        parent::__construct($title, $description, $authorNotes, $genreId, $iconId);
    }

    public static function fromForm(PublicationForm $form) : self 
    {
        if (!$form instanceof ArticleForm)
            throw new RuntimeException("ArticleForm instance is required");

        $parent = parent::fromForm($form);

        $contentData = $form->bookId !== NULL && $form->pageStart !== NULL && $form->pageEnd !== NULL
            ? ContentData::fromBook(
                new BookData(
                    $form->bookId,
                    pageStart: $form->pageStart,
                    pageEnd: $form->pageEnd
                )
            ) 
            : ($form->content !== NULL
                ? ContentData::fromContent($form->content)
                : ContentData::fromEmpty());

        return new self(
            title: $parent->title,
            description: $parent->description,
            authorNotes: $parent->authorNotes,
            genreId: $parent->genreId,
            iconId: $parent->iconId,
            typeId: $form->typeId,
            content: $contentData,
            doi: $form->doi,
        );
    }

    public function isBookBased() { return $this->contentData->getType() === ArticleContentType::Book; }
    public function isContentBased() { return $this->contentData->getType() === ArticleContentType::Content; }
}
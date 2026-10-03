<?php
namespace App\Models\Publications;

use DateTimeImmutable;

use App\Models\Uuid;
use App\Models\Users\UserShortData;
use App\Models\Publications\Publication;
use App\Models\BasicModel;

final readonly class Book extends PublicationExtended
{
    public function __construct(
        int $id,
        string $title,
        DateTimeImmutable $createdAt,
        ?Uuid $iconId,
        int $creatorId,
        int $genreId,
        ?BasicModel $genre,
        ?UserShortData $creator,
        int $commentsCount,
        int $savedCount,
        int $rating, // avg rating * 10
        string $description,
        string $authorNotes,
        private int $categoryId,
        public ?BasicModel $category,
        public string $publisher,
        public int $pagesCount,
        public string $isbn,
        public Uuid $contentId
    ){
        parent::__construct(
            $id, 
            $title, 
            $createdAt,
            $iconId, 
            $creatorId,
            $genreId,
            $genre,
            $creator,
            $commentsCount,
            $savedCount,
            $rating, 
            $description,
            $authorNotes
        );
    }

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        $c = 'c_';

        $parent = parent::fromRow($row, $prefix);
        return new self(
            id: $parent->id,
            title: $parent->title,
            createdAt: $parent->createdAt,
            iconId: $parent->iconId,
            description: $parent->description,
            authorNotes: $parent->authorNotes,
            commentsCount: $parent->commentsCount,
            savedCount: $parent->savedCount,
            rating: $parent->rating,
            creatorId: $parent->getCreatorId(),
            genreId: $parent->getGenreId(),
            genre: $parent->genre,
            creator: $parent->creator,
            categoryId: self::int($row, $prefix . 'category_id'),
            category: self::hasGroup($row, $c, 'id')
                ? BasicModel::fromRow($row, $prefix . $c) : NULL,
            publisher: self::str($row, $prefix . 'publisher'),
            pagesCount: self::int($row, $prefix . 'pages'),
            isbn: self::str($row, $prefix . 'isbn'),
            contentId: self::uuid($row, $prefix . 'content_id')
        );
    }

    public function getCategoryId() { return $this->categoryId; }
}
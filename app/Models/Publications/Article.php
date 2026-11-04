<?php
namespace App\Models\Publications;

use DateTimeImmutable;

use App\Models\Uuid;
use App\Models\Users\UserShortData;
use App\Models\Publications\Publication;
use App\Models\Publications\PublicationShort;
use App\Models\Publications\BookData;
use App\Models\Publications\ContentData;
use App\Models\Enums\ArticleContentMode;
use App\Models\BasicModel;

use App\Models\Enums\ArticleType;

readonly class Article extends PublicationExtended
{
    public function __construct(
        int $id,
        string $title,
        ?Uuid $iconId,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $deletedAt,
        int $creatorId,
        int $genreId,
        ?BasicModel $genre,
        ?UserShortData $creator,
        int $commentsCount,
        int $savedCount,
        int $rating, // avg rating * 10
        string $description,
        string $authorNotes,
        private int $typeId,
        public ?BasicModel $type,
        public ?string $doi,
        public ArticleContentMode $contentMode,
        private int $bookId,
        public ?PublicationShort $book
    ){
        parent::__construct(
            $id, 
            $title, 
            $createdAt,
            $deletedAt,
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
        $b = PublicationShort::ROW_PREFIX;
        $t = 't_';

        $parent = parent::fromRow($row, $prefix);
        return new self(
            id: $parent->id,
            title: $parent->title,
            iconId: $parent->iconId,
            createdAt: $parent->createdAt,
            deletedAt: $parent->deletedAt,
            description: $parent->description,
            authorNotes: $parent->authorNotes,
            commentsCount: $parent->commentsCount,
            savedCount: $parent->savedCount,
            rating: $parent->rating,
            creatorId: $parent->getCreatorId(),
            genreId: $parent->getGenreId(),
            genre: $parent->genre,
            creator: $parent->creator,
            typeId: self::int($row, $prefix . 'type_id'),
            type: self::hasGroup($row, $t, 'id')
                ? BasicModel::fromRow($row, $prefix . $t) : NULL,
            doi: self::strN($row, $prefix . 'doi'),
            bookId: self::intN($row, $prefix . 'book_id'),
            contentMode: self::str($row, $prefix . 'content_mode'),
            book: self::hasGroup($row, $b, 'id')
                ? PublicationShort::fromRow($row, $prefix . $b)
                : NULL
        );
    }

    public function toArray() : array
    {
        $parent = parent::toArray();
        return $parent + [
            'type' => $this->type,
            'doi' => $this->doi,
            'contentMode' => $this->contentMode,
            'book' => $this->book
        ];
    }
}
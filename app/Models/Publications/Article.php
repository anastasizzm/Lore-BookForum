<?php
namespace App\Models\Publications;

use DateTimeImmutable;

use App\Models\Uuid;
use App\Models\Users\UserShortData;
use App\Models\Publications\Publication;
use App\Models\Publications\PublicationShort;
use App\Models\BasicModel;

use App\Models\Enums\ArticleType;

final readonly class Article extends PublicationExtended
{
    public function __construct(
        int $id,
        string $title,
        ?Uuid $iconId,
        DateTimeImmutable $createdAt,
        int $creatorId,
        int $genreId,
        ?BasicModel $genre,
        ?UserShortData $creator,
        int $commentsCount,
        int $savedCount,
        int $rating, // avg rating * 10
        string $description,
        string $authorNotes,
        private ?int $book_id,
        public ?PublicationShort $book,
        private int $type_id,
        public ?BasicModel $type,
        public int $pageStart,
        public int $pageEnd,
        public string $doi,
        public ?string $content
    ){
        parent::__construct(
            $id, 
            $title, 
            $iconId, 
            $createdAt,
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
            description: $parent->description,
            authorNotes: $parent->authorNotes,
            commentsCount: $parent->commentsCount,
            savedCount: $parent->savedCount,
            rating: $parent->rating,
            creatorId: $parent->getCreatorId(),
            genreId: $parent->getGenreId(),
            genre: $parent->genre,
            creator: $parent->creator,
            bookId: self::intN($row, $prefix . 'book_id'),
            book: self::hasGroup($row, $b, 'id')
                ? PublicationShort::fromRow($row, $prefix . $b) : NULL,
            typeId: self::int($row, $prefix . 'type_id'),
            type: self::hasGroup($row, $t, 'id')
                ? BasicModel::fromRow($row, $prefix . $t) : NULL,
            doi: self::str($row, $prefix . 'doi'),
            contentId: self::uuid($row, $prefix . 'content_id')
        );
    }

    public function getBookId() { return $this->bookId; }
    public function getTypeId() { return $this->typeId; }

    public function isBookBased() { return $this->book_id !== null; }
    public function isContentBased() { return $this->content !== null; }
}
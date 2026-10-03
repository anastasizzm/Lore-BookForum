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
        public ArticleType $modelType,
        private ?int $book_id,
        public ?PublicationShort $book,
        public int $pageStart,
        public int $pageEnd,
        public string $doi,
        public string $content
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

        $parent = parent::fromRow($row, $prefix);
        $bookId = self::int($row, $prefix . 'book_id');
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
            bookId: $bookId,
            modelType: $bookId === null ? ArticleType::Content : ArticleType::Book,
            book: self::hasGroup($row, $b, 'id')
                ? PublicationShort::fromRow($row, $prefix . $b) : NULL,
            pagesCount: self::int($row, $prefix . 'pages'),
            doi: self::str($row, $prefix . 'doi'),
            contentId: self::uuid($row, $prefix . 'content_id')
        );
    }

    public function getBookId() { return $this->bookId; }
}
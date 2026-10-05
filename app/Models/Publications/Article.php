<?php
namespace App\Models\Publications;

use DateTimeImmutable;

use App\Models\Uuid;
use App\Models\Users\UserShortData;
use App\Models\Publications\Publication;
use App\Models\Publications\PublicationShort;
use App\Models\BasicModel;

use App\Models\Enums\ArticleType;

enum ArticleContentType
{
    case None;
    case Book;
    case Content;
}

final readonly class BookData
{
    public function __construct(
        public int $bookId,
        public ?PublicationShort $book,
        public int $pageStart,
        public int $pageEnd
    ){}
}

final readonly class ContentData
{
    public function __construct(
        private readonly ?BookData $bookData,
        private readonly ?string $content
    ){}

    public function getBookData() : ?BookData { return $this->bookData; }
    public function getContent() : ?string { return $this->content; }

    public static function fromBook(BookData $book) : self {return new self ($book, NULL);}
    public static function fromContent(string $content) : self {return new self (NULL, $content);}
    public static function fromEmpty() : self {return new self (NULL, NULL);}

    public function getType() : ArticleContentType
    {
        if ($this->bookData === NULL && $this->content === NULL)return ArticleContentType::None;
        if ($this->content === NULL) return ArticleContentType::Book;
        return ArticleContentType::Content;
    }
}

readonly class Article extends PublicationExtended
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
        private int $typeId,
        public ?BasicModel $type,
        public string $doi,
        public ContentData $contentData
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
        $b = PublicationShort::ROW_PREFIX;
        $t = 't_';

        $parent = parent::fromRow($row, $prefix);
        $bookId = self::intN($row, $prefix . 'book_id');
        $content = self::strN($row, $prefix . 'content');

        $contentData = $bookId !== NULL
            ? ContentData::fromBook(
                new BookData(
                    $bookId,
                    self::hasGroup($row, $b, 'id')
                        ? PublicationShort::fromRow($row, $prefix . $b) 
                        : NULL,
                    self::int($row, $prefix . 'page_start'),
                    self::int($row, $prefix . 'page_end')
                )
            ) 
            : ($content !== NULL
                ? ContentData::fromContent($content)
                : ContentData::fromEmpty());

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
            typeId: self::int($row, $prefix . 'type_id'),
            type: self::hasGroup($row, $t, 'id')
                ? BasicModel::fromRow($row, $prefix . $t) : NULL,
            doi: self::strN($row, $prefix . 'doi'),
            contentData: $contentData
        );
    }

    public function isBookBased() { return $this->contentData->getType() === ArticleContentType::Book; }
    public function isContentBased() { return $this->contentData->getType() === ArticleContentType::Content; }

    public function toArray() : array
    {
        $parent = parent::toArray();
        return $parent + [
            'type' => $this->type,
            'doi' => $this->doi,
            'contentData' => $this->contentData
        ];
    }
}
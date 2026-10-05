<?php
namespace App\Models\Publications;

use DateTimeImmutable;

use App\Models\Uuid;
use App\Models\Users\UserShortData;
use App\Models\Publications\Publication;
use App\Models\BasicModel;

readonly class PublicationExtended extends Publication
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
        public int $commentsCount,
        public int $savedCount,
        public int $rating, // avg rating * 10
        public string $description,
        public string $authorNotes,
    ){
        parent::__construct(
            $id, 
            $title, 
            $createdAt,
            $iconId, 
            $creatorId,
            $genreId,
            $genre,
            $creator);
    }

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        $parent = parent::fromRow($row, $prefix);
        return new self(
            id: $parent->id,
            title: $parent->title,
            iconId: $parent->iconId,
            createdAt: $parent->createdAt,
            creatorId: $parent->getCreatorId(),
            genreId: $parent->getGenreId(),
            creator: $parent->creator,
            genre: $parent->genre,
            commentsCount: self::int($row, $prefix . 'comments_count'),
            savedCount: self::int($row, $prefix . 'saved_count'),
            rating: self::int($row, $prefix . 'rating_avg'),
            description: self::str($row, $prefix . 'description'),
            authorNotes: self::str($row, $prefix . 'author_notes'),
        );
    }

    public function toArray() : array 
    {
        $parent = parent::toArray();
        return $parent + [
            'commentsCount' => $this->commentsCount,
            'savedCount' => $this->savedCount,
            'rating' => $this->rating,
            'description' => $this->descriptions,
            'authorNotes' => $this->authorNotes
        ];
    }
}
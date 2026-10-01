<?php
namespace App\Models\Publications;

use DateTimeImmutable;

use App\Models\Users\UserShortData;
use App\Models\Publications\PublicationShort;
use App\Models\BasicModel;

readonly class Publication extends PublicationShort
{
    public function __construct(
        int $id,
        string $title,
        int $iconId,
        DateTimeImmutable $createdAt,
        public int $commentsCount,
        public int $rating, // avg rating * 10
        private int $creatorId,
        private int $genreId,
        public ?BasicModel $genre,
        public ?UserShortData $creator
    ){
        parent::__construct($id, $title, $iconId, $createdAt);
    }

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        $u = UserShortData::ROW_PREFIX;
        $g = 'g_';

        $parent = parent::fromRow($row, $prefix);
        return new self(
            id: $parent->id,
            title: $parent->title,
            iconId: $parent->icon_id,
            createdAt: $parent->created_at,
            creatorId: self::int($row, $prefix . 'creator_id'),
            genreId: self::int($row, $prefix . 'genre_id'),
            commentsCount: self::int($row, $prefix . 'comments_count'),
            rating: self::int($row, $prefix . 'rating_avg'),
            creator: self::hasGroup($row, $prefix . $u, 'id')
                ? UserShortData::fromRow($row, $prefix . $u) : NULL,
            genre: self::hasGroup($row, $prefix . $g, 'id')
                ? BasicModel::fromRow($row, $prefix . $g) : NULL
        );
    }

    public function getCreatorId() { return $this->creatorId; }
    public function getGenreId() { return $this->genreId; }
}
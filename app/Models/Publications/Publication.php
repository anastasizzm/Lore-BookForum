<?php
namespace App\Models\Publications;

use DateTimeImmutable;

use App\Models\Uuid;
use App\Models\Users\UserShortData;
use App\Models\Publications\PublicationShort;
use App\Models\BasicModel;

readonly class Publication extends PublicationShort
{
    public function __construct(
        int $id,
        string $title,
        DateTimeImmutable $createdAt,
        ?Uuid $iconId,
        private int $creatorId,
        private int $genreId,
        public ?BasicModel $genre,
        public ?UserShortData $creator,
        /** В закладках у текущего пользователя (см. saved_publications в getList) */
        public bool $saved = false
    ){
        parent::__construct($id, $title, $createdAt, $iconId);
    }

    public static function fromRow(array $row, string $prefix = '') : self
    {
        $u = UserShortData::ROW_PREFIX;
        $g = 'g_';

        $parent = parent::fromRow($row, $prefix);
        return new self(
            id: $parent->id,
            title: $parent->title,
            iconId: $parent->iconId,
            createdAt: $parent->createdAt,
            creatorId: self::int($row, $prefix . 'creator_id'),
            genreId: self::int($row, $prefix . 'genre_id'),
            saved: self::boolN($row, $prefix . 'saved') ?? false,
            creator: self::hasGroup($row, $prefix . $u, 'id')
                ? UserShortData::fromRow($row, $prefix . $u) : NULL,
            genre: self::hasGroup($row, $prefix . $g, 'id')
                ? BasicModel::fromRow($row, $prefix . $g) : NULL
        );
    }

    public function getCreatorId() { return $this->creatorId; }
    public function getGenreId() { return $this->genreId; }
}

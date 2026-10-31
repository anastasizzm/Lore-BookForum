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
        ?DateTimeImmutable $deletedAt,
        ?Uuid $iconId,
        private int $creatorId,
        private int $ownerId,
        private int $genreId,
        public ?BasicModel $genre,
        public ?UserShortData $creator,
        public ?UserShortData $owner
    ){
        parent::__construct($id, $title, $createdAt, $deletedAt, $iconId);
    }

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        $u = UserShortData::ROW_PREFIX;
        $o = 'o_';
        $g = 'g_';

        $parent = parent::fromRow($row, $prefix);
        return new self(
            id: $parent->id,
            title: $parent->title,
            iconId: $parent->iconId,
            createdAt: $parent->createdAt,
            deletedAt: $parent->deletedAt,
            creatorId: self::int($row, $prefix . 'creator_id'),
            genreId: self::int($row, $prefix . 'genre_id'),
            ownerId: self::int($row, $prefix . 'owner_id'),
            creator: self::hasGroup($row, $prefix . $u, 'id')
                ? UserShortData::fromRow($row, $prefix . $u) : NULL,
            owner: self::hasGroup($row, $prefix . $o, 'id')
                ? UserShortData::fromRow($row, $prefix . $o) : NULL,
            genre: self::hasGroup($row, $prefix . $g, 'id')
                ? BasicModel::fromRow($row, $prefix . $g) : NULL
        );
    }

    public function getCreatorId() { return $this->creatorId; }
    public function getGenreId() { return $this->genreId; }
    public function getOwnerId() { return $this->ownerId; }

    public function toArray() : array 
    {
        $parent = parent::toArray();
        return $parent + [
            'genre' => $this->genre,
            'creator' => $this->creator,
            'owner' => $this->owner
        ];
    }
}
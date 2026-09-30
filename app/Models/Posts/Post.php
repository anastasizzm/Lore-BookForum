<?php
declare(strict_types=1);

namespace App\Models\Posts;

use DateTimeImmutable;
use App\Models\Users\UserShortData;
use App\Models\Dto;
use App\Models\Publications\Publication;

final readonly class Post extends Dto
{
    public function __construct(
        public int $id,
        public string $content,
        public bool $isActive,
        public int $likesCount,
        public int $commentsCount,
        private int $creatorId,
        private int $publicationId,
        public DateTimeImmutable $createdAt,
        public ?UserShortData $creator = null,
        public ?PublicationShort $publication = null,
    ) {}

    public static function fromRow(array $row, string $prefix = '') : self
    {
        $u = UserShortData::ROW_PREFIX;
        $p = PublicationShort::ROW_PREFIX;

        return new self(
            id: self::int($row, $prefix . 'id'),
            content: self::str($row, $prefix . 'content'),
            isActive: self::bool($row, $prefix . 'is_active'),
            createdAt: self::dt($row, $prefix . 'created_at'),
            likesCount: self::int($row, $prefix . 'likes_count'),
            commentsCount: self::int($row, $prefix . 'comments_count'),
            publicationId: self::int($row, $prefix . 'publication_id'),
            creatorId: self::int($row, $prefix . 'creator_id'),
            creator: self::hasGroup($row, $u, 'id')
                ? UserShortData::fromRow($row, $u) : null,
            publication: self::hasGroup($row, $p, 'id')
                ? PublicationShort::fromRow($row, $p) : null
        );
    }

    public function getCreatorId() : int {return $this->creatorId;}
    public function getPublicationId() : int {return $this->publicationId;}
}
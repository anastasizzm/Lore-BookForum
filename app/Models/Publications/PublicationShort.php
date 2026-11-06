<?php
declare(strict_types=1);

namespace App\Models\Publications;

use DateTimeImmutable;

use App\Models\Uuid;
use App\Models\BasicModel;

readonly class PublicationShort extends BasicModel
{
    public const ROW_PREFIX = 'pub_';

    public function __construct(
        int $id,
        string $title,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $deletedAt,
        public ?Uuid $iconId,
    ){
        parent::__construct($id, $title, $createdAt, $deletedAt);
    }

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        $parent = parent::fromRow($row, $prefix);
        return new self(
            id: $parent->id,
            title: $parent->title,
            createdAt: $parent->createdAt,
            deletedAt: $parent->deletedAt,
            iconId: self::uuidN($row, $prefix . 'icon_id'),
        );
    }

    public function toArray() : array 
    {
        $parent = parent::toArray();
        return $parent + [
            'iconId' => $this->iconId
        ];
    }
}
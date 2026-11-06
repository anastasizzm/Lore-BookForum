<?php
declare(strict_types=1);

namespace App\Models\Publications;

use DateTimeImmutable;

use App\Models\Uuid;
use App\Models\Enums\PublicationStatus;
use App\Models\BasicModel;

readonly class PublicationShort extends BasicModel
{
    public const ROW_PREFIX = 'pub_';

    public function __construct(
        int $id,
        string $title,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $deletedAt,
        public PublicationStatus $status,
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
            status: $parent->status,
            createdAt: $parent->createdAt,
            deletedAt: $parent->deletedAt,
            status: self::enum($row, $prefix . 'status', PublicationStatus::class),
            iconId: self::uuidN($row, $prefix . 'icon_id'),
        );
    }

    public function toArray() : array 
    {
        $parent = parent::toArray();
        return $parent + [
            'status' => $this->status->value,
            'iconId' => $this->iconId
        ];
    }
}
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
        public ?Uuid $iconId,
    ){
        parent::__construct($id, $title, $createdAt);
    }

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        $parent = parent::fromRow($row, $prefix);
        return new self(
            id: $parent->id,
            title: $parent->title,
            createdAt: $parent->createdAt,
            iconId: self::uuidN($row, $prefix . 'icon_id'),
        );
    }
}
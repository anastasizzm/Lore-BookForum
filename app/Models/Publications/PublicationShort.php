<?php
declare(strict_types=1);

namespace App\Models\Publications;

use DateTimeImmutable;
use Uuid;

use App\Models\BasicModel;

readonly class PublicationShort extends BasicModel
{
    public const ROW_PREFIX = 'pub_';

    public function __construct(
        int $id,
        string $title,
        public ?Uuid $iconId,
        public DateTimeImmutable $createdAt
    ){
        parent::__construct($id, $title);
    }

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        $parent = parent::fromRow($row, $prefix);
        return new self(
            id: $parent->id,
            title: $parent->title,
            iconId: self::uuidN($row, $prefix . 'icon_id'),
            createdAt: self::dt($row, $prefix . 'created_at'),
        );
    }
}
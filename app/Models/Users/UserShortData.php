<?php
declare(strict_types=1);

namespace App\Models\Users;

use App\Models\Dto;

readonly class UserShortData extends Dto
{
    public const ROW_PREFIX = 'u_';

    public function __construct(
        public int     $id,
        public string  $username,
        public string  $name,
        public string  $surname,
        public string  $avatar,
    ) {}

    public static function fromRow(string $row, string $prefix = '') : self
    {
        return new self(
            id: self::int($row, $prefix . 'id'),
            name: self::str($row, $prefix, 'name'),
            username: self::str($row, $prefix . 'username'),
            surname: self::str($row, $prefix . 'surname'),
            avatar: self::str($row, $prefix, 'avatar'),
        );
    }
}
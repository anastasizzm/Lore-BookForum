<?php
declare(strict_types=1);

namespace App\Models\Publications;
use App\Models\RowModel;

final readonly class BookData extends RowModel
{
    public function __construct(
        public int $bookId,
        public int $pageStart,
        public int $pageEnd
    ){}

    public static function fromRow(array $row, string $prefix = '') : self
    {
        return new self(
            bookId: self::int($row, $prefix . 'book_id'),
            pageStart: self::int($row, $prefix . 'page_start'),
            pageEnd: self::int($row, $prefix . 'page_end')
        );
    }
}
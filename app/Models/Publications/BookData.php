<?php
declare(strict_types=1);

namespace App\Models\Publications;

final readonly class BookData
{
    public function __construct(
        public int $bookId,
        public int $pageStart,
        public int $pageEnd
    ){}
}
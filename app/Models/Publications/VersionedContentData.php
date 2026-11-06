<?php
declare(strict_types=1);

namespace App\Models\Publications;
use App\Models\Publications\BookData;

final readonly class VersionedContentData extends ContentData
{
    public function __construct(
        ?BookData $bookData,
        ?string $content,
        public int $version
    ){}

    public static function fromBook(BookData $book, int $version) : self {return new self ($book, NULL, $version);}
    public static function fromContent(string $content, int $version) : self {return new self (NULL, $content, $version);}

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        $parent = parent::fromRow($row, $prefix);
        return new self($parent->getBookData(), $parent->getContent(), self::int($row, $prefix . 'version'));
    }

    public function toArray() : array 
    {
        return parent::toArray() + [
            'version' => $this->version
        ];
    }
}
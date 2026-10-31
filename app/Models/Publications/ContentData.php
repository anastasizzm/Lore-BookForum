<?php
declare(strict_types=1);

namespace App\Models\Publications;
use App\Models\Enums\ArticleContentMode;
use App\Models\Publications\BookData;
use App\Models\RowModel;

final readonly class ContentData extends RowModel
{
    public function __construct(
        private readonly ?BookData $bookData,
        private readonly ?string $content
    ){}

    public function getBookData() : ?BookData { return $this->bookData; }
    public function getContent() : ?string { return $this->content; }

    public static function fromBook(BookData $book) : self {return new self ($book, NULL);}
    public static function fromContent(string $content) : self {return new self (NULL, $content);}

    public function getType() : ArticleContentMode
    {
        if ($this->bookData !== NULL) return ArticleContentMode::Book;
        if ($this->content !== NULL) return ArticleContentMode::Text;

        throw new \RuntimException("Neither bookData or content provided");
    }

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        $mode = self::enum($row, $prefix . 'content_mode', ArticleContentMode::class);
        if ($mode === ArticleContentMode::Book) return new self(bookData: BookData::fromRow($row));
        else return new self(content: self::str($row, $prefix . 'content'));
    }
}
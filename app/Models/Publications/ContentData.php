<?php
declare(strict_types=1);

namespace App\Models\Publications;
use App\Models\Enums\ArticleContentMode;
use App\Models\Publications\BookData;

final readonly class ContentData
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
        if ($this->bookData !== NULL) return ArticleContentType::Book;
        if ($this->content !== NULL) return ArticleContentType::Text;

        throw new \RuntimException("Neither bookData or content provided");
    }
}
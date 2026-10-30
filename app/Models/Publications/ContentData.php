<?php
declare(strict_types=1);

namespace App\Models\Publications;
use App\Models\Enums\ArticleContentType;
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
    public static function fromEmpty() : self {return new self (NULL, NULL);}

    public function getType() : ArticleContentType
    {
        if ($this->bookData === NULL && $this->content === NULL)return ArticleContentType::None;
        if ($this->content === NULL) return ArticleContentType::Book;
        return ArticleContentType::Content;
    }
}
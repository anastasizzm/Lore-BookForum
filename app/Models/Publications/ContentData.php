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

        throw new \RuntimeException("Neither bookData or content provided");
    }

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        $mode = self::enum($row, $prefix . 'content_mode', ArticleContentMode::class);
        if ($mode === ArticleContentMode::Book)
            return new self(bookData: BookData::fromRow($row), content: null);

        // Для текстовой статьи content обязателен по CHECK-констрейнту,
        // но после UPDATE теоретически может быть NULL — не падаем.
        $content = array_key_exists($prefix . 'content', $row) && $row[$prefix . 'content'] !== null
            ? (string) $row[$prefix . 'content']
            : null;

        return new self(bookData: null, content: $content);
    }

    public function toArray() : array
    {
        return [
            'content' => $this->content,
            'book' => $this->bookData === null ? null : [
                'bookId' => $this->bookData->bookId,
                'pageStart' => $this->bookData->pageStart,
                'pageEnd' => $this->bookData->pageEnd,
            ],
        ];
    }
}
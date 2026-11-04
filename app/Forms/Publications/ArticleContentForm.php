<?php
declare(strict_types=1);

namespace App\Forms\Publications;
use App\Extensions\Parsers\QueryParser;
use App\Models\Uuid;
use App\Forms\Form;

readonly class ArticleContentForm implements Form
{
    public function __construct(
        public ?BookData $book,
        public ?string $content,
    ){}

    public static function fromInput(array $form) : self 
    {
        return new self(
            book: array_key_exists('book', $input)
                ? new BookData(
                    bookId: QueryParser::positiveInt($input['book'], 'bookId'),
                    pageStart: QueryParser::positiveInt($input['book'], 'pageStart'),
                    pageEnd: QueryParser::positiveInt($input['book'], 'pageEnd')
                )
                : NULL,
            content: QueryParser::optionalString($input, 'content'),
        );
    }
}
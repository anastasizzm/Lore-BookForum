<?php
declare(strict_types=1);

namespace App\Forms\Publications;

use App\Forms\Publications\PublicationForm;
use App\Extensions\Parsers\QueryParser;
use App\Models\Enums\ArticleContentMode;
use App\Models\Uuid;

final readonly class ArticleForm extends PublicationForm
{
    public function __construct(
        string $title,
        string $description,
        string $authorNotes,
        int $genreId,
        ?Uuid $iconId,
        public int $typeId,
        public ?BookData $book,
        public ?string $content,
        public ?string $doi
    ){
        parent::__construct($title, $description, $authorNotes, $genreId, $iconId);
    }

    public static function fromInput(array $input) : self
    {
        $parent = parent::fromInput($input);
        return new self(
            title: $parent->title,
            description: $parent->description,
            authorNotes: $parent->authorNotes,
            genreId: $parent->genreId,
            iconId: $parent->iconId,
            typeId: QueryParser::positiveInt($input, 'typeId'),
            book: array_key_exists('book', $input)
                ? new BookData(
                    bookId: QueryParser::positiveInt($input['book'], 'bookId'),
                    pageStart: QueryParser::positiveInt($input['book'], 'pageStart'),
                    pageEnd: QueryParser::positiveInt($input['book'], 'pageEnd')
                )
                : NULL,
            content: QueryParser::optionalString($input, 'content'),
            doi: QueryParser::optionalString($input, 'doi')
        );
    }
}
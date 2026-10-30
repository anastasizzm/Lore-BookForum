<?php
declare(strict_types=1);

namespace App\Forms\Publications;

use App\Forms\Publications\PublicationForm;
use App\Extensions\Parsers\QueryParser;
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
        public ?int $bookId,
        public ?int $pageStart,
        public ?int $pageEnd,
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
            bookId: QueryParser::optionalPositiveInt($input, 'bookId'),
            pageStart: QueryParser::optionalPositiveInt($input, 'pageStart'),
            pageEnd: QueryParser::optionalPositiveInt($input, 'pageEnd'),
            content: QueryParser::optionalString($input, 'content'),
            doi: QueryParser::optionalString($input, 'doi')
        );
    }
}
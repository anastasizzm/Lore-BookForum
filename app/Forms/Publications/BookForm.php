<?php
declare(strict_types=1);

namespace App\Forms\Publications;

use App\Forms\Publications\PublicationForm;
use App\Extensions\Parsers\QueryParser;

final readonly class BookModel extends PublicationModel
{
    public function __construct(
        string $title,
        string $description,
        string $authorNotes,
        int $genreId,
        public int $categoryId,
        public string $publisher,
        public int $pages,
        public ?string $isbn
    ){
        parent::__construct($title, $description, $authorNotes, $genreId);
    }

    public static function fromForm(BookForm $form) : self 
    {
        $parent = parent::fromForm($form);
        return new self(
            title: $parent->title,
            description: $parent->description,
            authorNotes: $parent->authorNotes,
            genreId: $parent->genreId,
            categoryId: $form->categoryId,
            publisher: $form->publisher,
            pages: $form->pages,
            isbn: $form->isbn,
        );
    }
}

final readonly class BookForm extends PublicationForm
{
    public function __construct(
        string $title,
        string $description,
        string $authorNotes,
        int $genreId,
        public int $categoryId,
        public string $publisher,
        public int $pages,
        public ?string $isbn
    ){
        parent::__construct($title, $description, $authorNotes, $genreId);
    }

    public static function fromInput(array $input) : self
    {
        $parent = parent::fromInput($input);
        return new self(
            title: $parent->title,
            description: $parent->description,
            authorNotes: $parent->authorNotes,
            genreId: $parent->genreId,
            categoryId: QueryParser::positiveInt($input, 'categoryId'),
            publisher: QueryParser::string($input, 'publisher'),
            pages: QueryParser::posiviteInt($input, 'pages'),
            isbn: QueryParser::optionalString($input, 'isbn')
        );
    }
}
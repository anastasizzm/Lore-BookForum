<?php
declare(strict_types=1);

namespace App\Forms\Publications;

use App\Forms\Publications\PublicationForm;
use App\Extensions\Parsers\QueryParser;
use App\Models\Uuid;

final readonly class BookForm extends PublicationForm
{
    public function __construct(
        string $title,
        string $description,
        string $authorNotes,
        int $genreId,
        ?Uuid $iconId,
        public int $categoryId,
        public string $publisher,
        public int $pages,
        public ?string $isbn,
        public array $contentIds
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
            categoryId: QueryParser::positiveInt($input, 'categoryId'),
            publisher: QueryParser::string($input, 'publisher'),
            pages: QueryParser::positiveInt($input, 'pages'),
            isbn: QueryParser::optionalString($input, 'isbn'),
            contentIds: self::parseContentIds($input)
        );
    }

    private static function parseContentIds(array $body): array
    {
        $raw = $body['contentIds'] ?? [];

        if (!is_array($raw)){
            $raw = [$raw];
        }

        $result = [];
        $seen   = [];

        foreach ($raw as $value) {
            if (!is_string($value) || $value === '') {
                continue;
            }

            try {
                $uuid = Uuid::fromString($value);
            } catch (\InvalidArgumentException) {
                continue;
            }

            $key = $uuid->toString();
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $uuid;
        }

        return $result;
    }
}
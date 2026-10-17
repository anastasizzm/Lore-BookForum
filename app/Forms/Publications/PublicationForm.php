<?php
declare(strict_types=1);

namespace App\Forms\Publications;

use App\Forms\Form;
use App\Extensions\Parsers\QueryParser;
use App\Models\Uuid;

readonly class PublicationForm implements Form
{
    public function __construct(
        public string $title,
        public string $description,
        public string $authorNotes,
        public int $genreId,
        public ?Uuid $iconId
    ){}

    public static function fromInput(array $input) : self
    {
        return new self(
            title: QueryParser::string($input, 'title'),
            description: QueryParser::string($input, 'description'),
            authorNotes: QueryParser::string($input, 'authorNotes'),
            genreId: QueryParser::positiveInt($input, 'genreId'),
            iconId: QueryParser::optionalUuid($input, 'iconId')
        );
    }
}
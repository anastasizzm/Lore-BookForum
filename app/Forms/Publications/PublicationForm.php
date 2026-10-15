<?php
declare(strict_types=1);

namespace App\Forms\Publications;

use App\Forms\Form;
use App\Extensions\Parsers\QueryParser;
use App\Models\Uuid;

final readonly class PublicationModel
{
    public function __construct(
        public string $title,
        public string $description,
        public string $authorNotes,
        public int $genreId,
        public ?Uuid $iconId
    ){}

    public static function fromForm(PublicationForm $form) : self 
    {
        return new self(
            title: $form->title,
            description: $form->description,
            authorNotes: $form->authorNotes,
            genreId: $form->genreId,
            iconId: $form->iconId
        );
    }
}

final readonly class PublicationForm implements Form
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
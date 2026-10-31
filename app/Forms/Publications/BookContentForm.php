<?php
declare(strict_types=1);

namespace App\Forms\Publications;
use App\Extensions\Parsers\QueryParser;
use App\Models\Uuid;
use App\Forms\Form;

readonly class BookContentForm implements Form
{
    public function __construct(
        public Uuid $contentId
    ){}

    public static function fromInput(array $form) : self 
    {
        return new self(
            contentId: QueryParser::uuid($form, 'contentId')
        );
    }
}
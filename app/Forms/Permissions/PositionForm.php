<?php
declare(strict_types=1);

namespace App\Forms\Permissions;
use App\Forms\Form;
use App\Extensions\Parsers\QueryParser;

readonly class PositionForm implements Form
{
    public function __construct(
        public ?int $belowId,
        public ?int $aboveId
    ){}

    public static function fromInput(array $form) : self 
    {
        return new self(
            belowId: QueryParser::optionalPositiveInt($form, 'belowId'),
            aboveId: QueryParser::optionalPositiveInt($form, 'aboveId')
        );
    }
}
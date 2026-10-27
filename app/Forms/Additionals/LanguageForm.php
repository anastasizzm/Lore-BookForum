<?php
declare(strict_types=1);

namespace App\Forms\Additionals;
use App\Forms\Form;
use App\Extensions\QueryParser;

final readonly class LanguageForm implements Form
{
    public function __construct(
        public string $code
    ){}

    public static function fromInput(array $form) : self 
    {
        return new self(
            code: QueryParser::string($form, 'code')
        );
    }
}
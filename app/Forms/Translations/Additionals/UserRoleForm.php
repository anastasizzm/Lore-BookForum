<?php
declare(strict_types=1);

namespace App\Forms\Translations\Additionals;

use App\Forms\Translations\TranslationsForm;
use App\Extensions\Parsers\QueryParser;
use App\Forms\Translations\ParsesTranslations;

final readonly class UserRoleForm implements TranslationsForm
{
    use ParsesTranslations;

    /** @param array<string, string> $translations */
    public function __construct(
        public string $color,
        public bool $isPublic,
        public array $translations
    ){}

    public static function fromInput(array $form) : self
    {
        return new self(
            color: QueryParser::string($form, 'color'),
            isPublic: QueryParser::bool($form, 'isPublic'),
            translations: self::parseTranslations($form)
        );
    }

}

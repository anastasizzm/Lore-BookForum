<?php
declare(strict_types=1);

namespace App\Forms\Translations\Additionals;

use App\Forms\Translations\TranslationsForm;
use App\Forms\Translations\ParsesTranslations;

final readonly class GenreForm implements TranslationsForm
{
    use ParsesTranslations;

    /** @param array<string, string> $translations */
    public function __construct(
        public array $translations
    ){}

    public static function fromInput(array $form) : self
    {
        return new self(self::parseTranslations($form));
    }

}

<?php
declare(strict_types=1);

namespace App\Forms\Translations\Limits\Additionals;

use App\Forms\Translations\Limits\TranslationsLimit;

final readonly class CategoryFormLimits implements TranslationsLimit
{
    public const int TITLE_MIN_LEN = 1;
    public const int TITLE_MAX_LEN = 255;
    public const int MIN_TRANSLATIONS = 1;

    public function titleMinLen() : int { return self::TITLE_MIN_LEN; }
    public function titleMaxLen() : int { return self::TITLE_MAX_LEN; }
    public function minTranslations() : int { return self::MIN_TRANSLATIONS; }

    public function all() : array
    {
        return [
            'title' => [
                'minLen' => $this->titleMinLen(),
                'maxLen' => $this->titleMaxLen(),
            ],
            'translations' => [
                'min' => $this->minTranslations(),
            ],
        ];
    }
}
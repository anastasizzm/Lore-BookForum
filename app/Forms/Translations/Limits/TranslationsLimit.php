<?php
declare(strict_types=1);

namespace App\Forms\Translations\Limits;

interface TranslationsLimit
{
    /** Минимальная длина названия перевода */
    public function titleMinLen() : int;

    /** Максимальная длина названия перевода */
    public function titleMaxLen() : int;

    /** Минимальное количество переводов в форме */
    public function minTranslations() : int;

    /**
     * Все лимиты одним массивом (например, для ответа /rules).
     * @return array{title: array{minLen: int, maxLen: int}, translations: array{min: int}}
     */
    public function all() : array;
}
<?php
declare(strict_types=1);

namespace App\Forms\Translations;

use App\Forms\Form;

/**
 * Форма сущности с переводами названия (genres, categories, types).
 * Реализации хранят их в public array $translations (код языка => название).
 *
 * Входной формат: {"translations": {"en": "Fantasy", "ru": "Фэнтези"}}
 */
interface TranslationsForm extends Form
{
}
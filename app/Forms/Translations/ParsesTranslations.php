<?php
declare(strict_types=1);

namespace App\Forms\Translations;

/**
 * Общий разбор поля translations для форм Genre/Category/Type.
 * Ничего не отбрасывает молча: невалидные значения доходят до валидатора.
 */
trait ParsesTranslations
{
    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private static function parseTranslations(array $input) : array
    {
        $raw = $input['translations'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $result = [];
        foreach ($raw as $code => $title) {
            $code = strtolower(trim((string)$code));
            $result[$code] = is_string($title) ? trim($title) : '';
        }

        return $result;
    }
}

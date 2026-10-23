<?php
declare(strict_types=1);

namespace App\Validators;

use App\Forms\Translations\Limits\TranslationsLimit;
use App\Lib\I18n\Translator;
use App\Models\Errors\ErrorBag;

final class TranslationsValidator
{
    public const string CODE_REG_EX = '#^[a-z]{2}$#';

    public function __construct(
        private readonly Translator $translator
    ){}

    public function validateTranslations(
        array $translations,
        TranslationsLimit $limits,
        string $label,
        ErrorBag $bag
    ) : bool
    {
        if (count($translations) < $limits->minTranslations()) {
            $bag->add($label, [$this->translator->t('errors.common.min_len', [':value' => $limits->minTranslations()])]);
            return false;
        }

        $ok = true;
        foreach ($translations as $code => $title) {
            $field = $label . '.' . $code;

            if (!preg_match(self::CODE_REG_EX, (string)$code)) {
                $bag->add($field, [$this->translator->t('errors.common.invalid')]);
                $ok = false;
                continue;
            }

            $length = mb_strlen($title);

            if ($length < $limits->titleMinLen()) {
                $bag->add($field, [$this->translator->t('errors.common.min_len', [':value' => $limits->titleMinLen()])]);
                $ok = false;
            } elseif ($length > $limits->titleMaxLen()) {
                $bag->add($field, [$this->translator->t('errors.common.max_len', [':value' => $limits->titleMaxLen()])]);
                $ok = false;
            }
        }

        return $ok;
    }
}
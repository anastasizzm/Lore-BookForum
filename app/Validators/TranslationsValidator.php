<?php
declare(strict_types=1);

namespace App\Validators;

use App\Forms\Translations\Limits\TranslationsLimit;
use App\Lib\I18n\Translator;
use App\Lib\Settings\Settings;
use App\Models\Errors\ErrorBag;

final class TranslationsValidator
{
    public const string CODE_REG_EX = '#^[a-z]{2}$#';

    public function __construct(
        private readonly Translator $translator,
        private readonly Settings $settings
    ){}

    public function validateTranslations(
        array $translations,
        TranslationsLimit $limits,
        string $label,
        ErrorBag $bag
    ) : bool
    {
        $defaultLang = $this->settings->i18n->default;
        $ok = true;

        // 1. Проверка дефолтного языка на пустоту
        if (isset($translations[$defaultLang]) && trim((string)$translations[$defaultLang]) === '') {
            $bag->add($label . '.' . $defaultLang, [$this->translator->t('errors.common.required')]);
            $ok = false;
        }

        // 2. Проверка минимального количества
        if (count($translations) < $limits->minTranslations()) {
            $bag->add($label, [$this->translator->t('errors.common.min_len', [':value' => $limits->minTranslations()])]);
            return false;
        }

        foreach ($translations as $code => $title) {
            $field = $label . '.' . $code;

            if (!preg_match(self::CODE_REG_EX, (string)$code)) {
                $bag->add($field, [$this->translator->t('errors.common.invalid')]);
                $ok = false;
                continue;
            }

            $length = mb_strlen($title);

            if ($length < $limits->titleMinLen()) {
                // Если это дефолтный язык и мы уже добавили ошибку required, не дублируем min_len
                if ($code === $defaultLang && $length === 0) {
                    continue;
                }
                
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
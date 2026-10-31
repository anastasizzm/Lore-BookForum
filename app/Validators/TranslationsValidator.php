<?php
declare(strict_types=1);

namespace App\Validators;

use App\Forms\Translations\Limits\TranslationsLimit;
use App\Validators\Additionals\LanguageFormValidator;
use App\Lib\I18n\Translator;
use App\Lib\Settings\Settings;
use App\Models\Errors\ErrorBag;

final class TranslationsValidator
{
    public function __construct(
        private readonly Translator $translator,
        private readonly Settings $settings
    ){}

    /** @param array<string, string> $translations */
    public function validateTranslations(
        array $translations,
        TranslationsLimit $limits,
        string $label,
        ErrorBag $bag
    ) : bool
    {
        $defaultLang = $this->settings->i18n->default;
        $ok = true;

        if (isset($translations[$defaultLang]) && trim((string)$translations[$defaultLang]) === '') {
            $bag->add($label . '.' . $defaultLang, [$this->translator->t('errors.common.required')]);
            $ok = false;
        }

        if (count($translations) < $limits->minTranslations()) {
            $bag->add($label, [$this->translator->t('errors.common.min_len', [':value' => $limits->minTranslations()])]);
            return false;
        }

        foreach ($translations as $code => $title) {
            $field = $label . '.' . $code;

            if (!preg_match(LanguageFormValidator::CODE_REGEX, (string)$code)) {
                $bag->add($field, [$this->translator->t('errors.common.format')]);
                $ok = false;
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
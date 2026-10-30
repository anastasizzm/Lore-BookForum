<?php
declare(strict_types=1);

namespace App\Validators\Publications;

use App\Validators\Publications\PublicationFormValidator;
use App\Lib\I18n\Translator;
use App\Models\Errors\ErrorBag;
use App\Lib\Settings\Settings;

/**
 * @extends Validator<ArticleForm>
 */
final class ArticleFormValidator extends PublicationFormValidator 
{
    public const string DOI_REG_EX = "#^10.\d{4,9}\/[-._;()\/:A-Z0-9]+$#";

    public function __construct(Settings $settings, Translator $translator)
    {
        parent::__construct($settings, $translator);
    }

    public function validate(mixed $item, ErrorBag $bag) : bool 
    {
        $ok = parent::validate($item, $bag);

        if ($item->book !== NULL)
        {
            $book = $item->book;
            if ($item->content !== NULL)
            {
                $bag->add('content', [$this->translator->t("errors.articles.both_provided")]);
                $ok = false;
            }

            if ($book->pageStart <= 0)
            {
                $bag->add('pageStart', [$this->translator->t("errors.common.required")]);
                $ok = false;
            }

            if ($book->pageEnd <= 0)
            {
                $bag->add('pageEnd', [$this->translator->t("errors.common.required")]);
                $ok = false;
            }
        }
        else if ($item->content !== NULL)
        {
            if ($item->book !== NULL)
            {
                $bag->add('book', [$this->translator->t("errors.articles.both_provided")]);
                $ok = false;
            }
        }
        else{
            $bag->add('content', [$this->translator->t("errors.common.required")]);
            $ok = false;
        }

        $doi = $item->doi;
        if ($doi !== NULL && !preg_match(self::DOI_REG_EX, $doi, $m)){
            $bag->add('doi', [$this->translator->t('errors.articles.doi_format')]);
            $ok = false;
        }

        return (bool)$ok;
    }
}
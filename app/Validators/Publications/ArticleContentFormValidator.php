<?php
declare(strict_types=1);

namespace App\Validators\Publications;

use App\Validators\Validator;
use App\Lib\I18n\Translator;
use App\Models\Errors\ErrorBag;
use App\Lib\Settings\Settings;

/**
 * @extends Validator<ArticleContentForm>
 */
final class ArticleContentFormValidator extends Validator 
{
    public function __construct(Settings $settings, Translator $translator)
    {
        parent::__construct($settings, $translator);
    }

    public function validate(mixed $item, ErrorBag $bag) : bool 
    {
        $ok = true;

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

        return $ok;
    }
}
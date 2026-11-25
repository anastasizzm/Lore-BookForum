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
    /** Максимум страниц в отрывке (совпадает с LIMITS.excerptPages во фронте). */
    public const int MAX_EXCERPT_PAGES = 30;

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

            // Диапазон отрывка: конец не раньше начала, не длиннее лимита.
            if ($book->pageStart > 0 && $book->pageEnd > 0)
            {
                if ($book->pageEnd < $book->pageStart)
                {
                    $bag->add('pageEnd', [$this->translator->t("errors.article.rule_article_fragment_order")]);
                    $ok = false;
                }
                elseif ($book->pageEnd - $book->pageStart + 1 > self::MAX_EXCERPT_PAGES)
                {
                    $bag->add('pageEnd', [$this->translator->t("errors.article.rule_article_fragment_long", ['max' => self::MAX_EXCERPT_PAGES])]);
                    $ok = false;
                }
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
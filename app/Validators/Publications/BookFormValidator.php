<?php
declare(strict_types=1);

namespace App\Validators\Publications;

use App\Validators\Publications\PublicationFormValidator;
use App\Lib\I18n\Translator;
use App\Models\Errors\ErrorBag;
use App\Lib\Settings\Settings;

/**
 * @extends Validator<BookForm>
 */
final class BookFormValidator extends PublicationFormValidator 
{
    public const int PUBLISHER_MAX_LEN = 255;
    public const string ISBN_REG_EX = "#^\d{3}-\d{1}-\d{3}-\d{5}-\d{1}$#";

    public function __construct(Settings $settings, Translator $translator)
    {
        parent::__construct($settings, $translator);
    }

    public function validate(mixed $item, ErrorBag $bag) : bool 
    {
        $ok = parent::validate($item, $bag);

        $ok &= $this->checkStrings($bag, $item->publisher, 1, self::PUBLISHER_MAX_LEN, 'publisher');

        if ($item->pages < 1){
            $bag->add('pages', [$this->translator->t('errors.common.min', [':value' => 1])]);
            $ok = false;
        }

        $isbn = $item->isbn;
        if ($isbn !== NULL && !preg_match(self::ISBN_REG_EX, $isbn, $m)){
            $bag->add('isbn', [$this->translator->t('errors.books.isbn_format')]);
            $ok = false;
        }

        if (count($item->contentIds) < 1){
            $bag->add('contentIds', [$this->translator->t('errors.common.min_len', [":value" => 1])]);
            $ok = false;
        }

        return $ok;
    }
}
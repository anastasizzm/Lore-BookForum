<?php
declare(strict_types=1);

namespace App\Validators\Publications;

use App\Validators\Validator;
use App\Lib\I18n\Translator;
use App\Models\Errors\ErrorBag;

/**
 * @extends Validator<BookForm>
 */
final class BookFormValidator extends Validator 
{
    public const int PUBLISHER_MAX_LEN = 255;
    public const string ISBN_REG_EX = "#^\d{3}-\d{1}-\d{3}-\d{5}-\d{1}$#";

    public function __construct(Translator $translator)
    {
        parent::__construct($translator);
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
            $bag->add('isbn', [$this->translator->t('errors.common.format')]);
            $ok = false;
        }

        return $ok;
    }
}
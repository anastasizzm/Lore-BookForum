<?php
declare(strict_types=1);

namespace App\Validators\Publications;

use App\Validators\Publications\PublicationFormValidator;
use App\Lib\I18n\Translator;
use App\Models\Errors\ErrorBag;
use App\Lib\Settings\Settings;

/**
 * @extends Validator<BookPatchForm>
 */
final class BookPatchFormValidator extends PublicationPatchFormValidator 
{
    public function __construct(Settings $settings, Translator $translator)
    {
        parent::__construct($settings, $translator);
    }

    public function validate(mixed $item, ErrorBag $bag) : bool 
    {
        $ok = parent::validate($item, $bag);

        if ($item->hasPublisher)
            $ok &= $this->checkStrings($bag, $item->publisher, 1, BookFormValidator::PUBLISHER_MAX_LEN, 'publisher');

        if ($item->hasPages && $item->pages < 1){
            $bag->add('pages', [$this->translator->t('errors.common.min', [':value' => 1])]);
            $ok = false;
        }

        $isbn = $item->isbn;
        if ($item->hasIsbn && $isbn !== NULL && !preg_match(BookFormValidator::ISBN_REG_EX, $isbn, $m)){
            $bag->add('isbn', [$this->translator->t('errors.books.isbn_format')]);
            $ok = false;
        }

        return (bool)$ok;
    }
}
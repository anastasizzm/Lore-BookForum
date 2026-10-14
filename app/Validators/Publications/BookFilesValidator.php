<?php
declare(strict_types=1);

namespace App\Validators\Publications;

use App\Validators\FilesValidator;
use App\Validators\Validator;
use App\Lib\I18n\Translator;
use App\Models\Errors\ErrorBag;

/**
 * @extends Validator<BookFiles>
 */
final class BookFilesValidator extends Validator 
{
    private const string BOOK_CATEGORY = 'book';
    private const string ICON_CATEGORY = 'cover';

    public function __construct(
        Settings $settings, 
        Translator $translator,
        FilesValidator $filesValidator)
    {
        parent::__construct($settings, $translator);
    }

    public function validate(mixed $item, ErrorBag $bag) : bool 
    {
        $ok = true;
        if ($item->icon !== NULL)
            $ok &= $this->filesValidator->validateOneFile($item->icon, self::ICON_CATEGORY, 'icon', $bag);

        $ok &= $this->filesValidator->validateManyFiles($item->contents, self::BOOK_CATEGORY, 'contents', $bag);
        return $ok;
    }
}
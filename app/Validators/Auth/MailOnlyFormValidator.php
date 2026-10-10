<?php
declare(strict_types=1);

namespace App\Validators\Auth;

use App\Validators\Validator;
use App\Lib\I18n\Translator;
use App\Models\Errors\ErrorBag;

/**
 * @extends Validator<MailOnlyForm>
 */
final class MailOnlyFormValidator extends Validator 
{
    public function __construct(Translator $translator)
    {
        parent::__construct($translator);
    }

    public function validate(mixed $item, ErrorBag $bag) : bool 
    {
        $ok = true;
        
        if (!filter_var($item->email, FILTER_VALIDATE_EMAIL)) {
            $bag->add('email', [$this->translator->t("errors.account.email_format")]);
            $ok = false;
        }

        return $ok;
    }
}
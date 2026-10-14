<?php
declare(strict_types=1);

namespace App\Validators\Auth;

use App\Validators\Validator;
use App\Lib\I18n\Translator;
use App\Models\Errors\ErrorBag;

/**
 * @extends Validator<PassResetForm>
 */
final class PassResetFormValidator extends Validator 
{
    public function __construct(Settings $settings, Translator $translator)
    {
        parent::__construct($settings, $translator);
    }

    public function validate(mixed $item, ErrorBag $bag) : bool 
    {
        $ok = true;
        
        if (empty($item->token))
        {
            $bag->add('token', [$this->translator->t("errors.account.token_empty")]);
            $ok = false;
        }

        if (strlen($item->password) < 8) {
            $bag->add('password', [$this->translator->t("errors.account.common.min_len", [":value" => 8])]);
            $ok = false;
        }

        return $ok;
    }
}
<?php
declare(strict_types=1);

namespace App\Validators\Publications;

use App\Validators\Validator;
use App\Lib\I18n\Translator;
use App\Models\Errors\ErrorBag;

/**
 * @extends Validator<PostForm>
 */
final class PostFormValidator extends Validator 
{
    public function __construct(Translator $translator)
    {
        parent::__construct($translator);
    }

    public function validate(mixed $item, ErrorBag $bag) : bool 
    {
        $ok = true;

        if (empty($item->publicationId))
        {
            $bag->add('publicationId', [$this->translator->t('errors.common.required')]);
            $ok = false;
        }

        $lenStr = strlen($item->content);
        if ($lenStr < 1 || $lenStr > 2000)
        {
            $bag->add('content', [$this->translator->t('errors.common.between_len', [':from' => 1, ':to' => 2000])]);
            $ok = false;
        }

        return $ok;
    }
}
<?php
declare(strict_types=1);

namespace App\Validators\Publications;

use App\Validators\Publications\PublicationPatchFormValidator;
use App\Lib\I18n\Translator;
use App\Models\Errors\ErrorBag;
use App\Lib\Settings\Settings;

/**
 * @extends Validator<ArticlePatchForm>
 */
final class ArticlePatchFormValidator extends PublicationPatchFormValidator 
{
    public const string DOI_REG_EX = "#^10.\d{4,9}\/[-._;()\/:A-Z0-9]+$#";

    public function __construct(Settings $settings, Translator $translator)
    {
        parent::__construct($settings, $translator);
    }

    public function validate(mixed $item, ErrorBag $bag) : bool 
    {
        $ok = parent::validate($item, $bag);

        $doi = $item->doi;
        if ($doi !== NULL && !preg_match(self::DOI_REG_EX, $doi, $m)){
            $bag->add('doi', [$this->translator->t('errors.articles.doi_format')]);
            $ok = false;
        }

        return (bool)$ok;
    }
}
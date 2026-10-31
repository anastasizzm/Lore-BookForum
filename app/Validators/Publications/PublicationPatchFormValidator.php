<?php
declare(strict_types=1);

namespace App\Validators\Publications;

use App\Validators\Validator;
use App\Lib\I18n\Translator;
use App\Models\Errors\ErrorBag;
use App\Lib\Settings\Settings;

/**
 * @extends Validator<PublicationPatchForm>
 */
class PublicationPatchFormValidator extends Validator 
{
    public function __construct(Settings $settings, Translator $translator)
    {
        parent::__construct($settings, $translator);
    }

    public function validate(mixed $item, ErrorBag $bag) : bool 
    {
        $ok = true;

        if($item->hasTitle)
            $ok &= $this->checkStrings($bag, $item->title, 1, PublicationFormValidator::TITLE_MAX_LEN, 'title');

        if($item->hasDescription)
            $ok &= $this->checkStrings($bag, $item->description, 1, PublicationFormValidator::DESCR_MAX_LEN, 'description');
        
        if($item->hasAuthorNotes)
            $ok &= $this->checkStrings($bag, $item->authorNotes, 1, PublicationFormValidator::NOTES_MAX_LEN, 'authorNotes');

        if ($item->hasGenreId && $item->genreId < 1){
            $bag->add('genreId', [$this->translator->t('errors.common.min', [':value' => 1])]);
            $ok = false;
        }
        
        return (bool)$ok;
    }

    protected function checkStrings(ErrorBag $bag, string $value, int $minLen, int $maxLen, string $label) : bool 
    {
        $len = strlen($value);
        if ($len < $minLen || $len > $maxLen){
            $bag->add($label, [$this->translator->t('errors.common.between_len', [':from' => $minLen, ':to' => $maxLen])]);
            return false;
        }
        return true;
    }
}
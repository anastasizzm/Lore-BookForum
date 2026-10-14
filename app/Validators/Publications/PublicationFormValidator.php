<?php
declare(strict_types=1);

namespace App\Validators\Publications;

use App\Validators\Validator;
use App\Lib\I18n\Translator;
use App\Models\Errors\ErrorBag;

/**
 * @extends Validator<PublicationForm>
 */
class PublicationFormValidator extends Validator 
{
    public const int TITLE_MAX_LEN = 255;
    public const int DESCR_MAX_LEN = 4000;
    public const int NOTES_MAX_LEN = 2000;

    public function __construct(Settings $settings, Translator $translator)
    {
        parent::__construct($settings, $translator);
    }

    public function validate(mixed $item, ErrorBag $bag) : bool 
    {
        $ok = true;

        $ok &= $this->checkStrings($bag, $item->title, 1, self::TITLE_MAX_LEN, 'title');
        $ok &= $this->checkStrings($bag, $item->description, 1, self::DESCR_MAX_LEN, 'description');
        $ok &= $this->checkStrings($bag, $item->authorNotes, 1, self::NOTES_MAX_LEN, 'authorNotes');

        if ($item->genreId < 1){
            $bag->add('genreId', [$this->translator->t('errors.common.min', [':value' => 1])]);
            $ok = false;
        }
        
        return $ok;
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
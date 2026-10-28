<?php
declare(strict_types=1);

namespace App\Validators\Publications;

use App\Validators\Validator;
use App\Lib\I18n\Translator;
use App\Models\Enums\BookFormat;
use App\Models\Errors\ErrorBag;
use App\Lib\Settings\Settings;

/**
 * @extends Validator<ReadProgressForm>
 */
final class ReadProgressFormValidator extends Validator 
{
    public function __construct(Settings $settings, Translator $translator)
    {
        parent::__construct($settings, $translator);
    }

    public function validate(mixed $item, ErrorBag $bag) : bool 
    {
        $ok = true;

        $position = $item->position;
        $format = $item->format;
        if ($format === BookFormat::Pdf) {
            $pageNumber = $position['pageNumber'] ?? null;
            
            if (!is_int($pageNumber) || $pageNumber < 1){
                $bag->add('position.pageNumber', [$this->translator->t('errors.common.min', [':value' => 1])]);
                $ok = false;
            }
        } elseif ($format === BookFormat::Epub) {
            $chapterIndex = $position['chapterIndex'] ?? null;

            if (!is_int($chapterIndex) || $chapterIndex < 0) {
                $bag->add('position.chapterIndex', [$this->translator->t('errors.common.min', [':value' => 0])]);
                $ok = false;
            }
        }


        return (bool)$ok;
    }
}
<?php
declare(strict_types=1);

namespace App\Validators;

use App\Models\Errors\ErrorBag;
use App\Lib\I18n\Translator;

/**
 * @template T
 */
abstract class Validator 
{
    public function __construct(
        protected Translator $translator
    ){}

    /**
     * @param T $item
     * @param ErrorBag $bag
     */
    public abstract function validate(mixed $item, ErrorBag $bag) : bool;

    /**
     * @param T $item
     */
    public function validateOne(mixed $item) : ErrorBag 
    {
        $bag = new ErrorBag();
        $this->validate($item, $bag);
        return $bag;
    }
}
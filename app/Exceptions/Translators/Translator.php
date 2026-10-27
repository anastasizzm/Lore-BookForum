<?php
declare(strict_types=1);

namespace App\Exceptions\Translators;

use Throwable;
use PDOException;
use App\Exceptions\ValidationException;
use App\Lib\I18n\Translator as I18n;

abstract class Translator
{
    public function __construct(
        private readonly I18n $translator
    ){}

    public abstract function translate(PDOException $e) : Throwable;

    protected function raiseException(array $entry) : Throwable
    {
        [$field, $messageKey] = $entry;
        $params = $entry[2] ?? [];

        return new ValidationException([$field => [$this->translator->t($messageKey, $params)]]);
    }
}
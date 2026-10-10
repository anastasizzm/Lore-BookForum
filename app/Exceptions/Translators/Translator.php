<?php
declare(strict_types=1);

namespace App\Exceptions\Translators;

use Throwable;
use PDOException;

abstract class Translator
{
    public abstract function translate(PDOException $e) : Throwable;

    private function raiseException(array $entry) : Throwable
    {
        [$field, $messageKey] = $entry;
        $params = $entry[3] ?? [];

        return new ValidationException([$field => [$this->translator->t($messageKey, $params)]]);
    }
}
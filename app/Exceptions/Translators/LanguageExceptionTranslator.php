<?php
declare(strict_types=1);

namespace App\Exceptions\Translators;

use App\Exceptions\Translators\Translator as ExceptionTranslator;
use App\Exceptions\ValidationException;
use App\Extensions\PdoExtensions;
use App\Lib\I18n\Translator as I18n;
use PDOException;
use Throwable;

final class LanguageExceptionTranslator extends ExceptionTranslator
{
    /** UNIQUE: constraint → [поле, сообщение] */
    private const UNIQUE = [
        'languages_code_key' => ['code', 'errors.languages.already_exists'],
    ];

    /** NOT NULL: колонка → [поле, сообщение] */
    private const NOT_NULL = [
        'code'      => ['code',      'errors.common.required'],
        'is_active' => ['is_active', 'errors.common.required'],
    ];

    public function __construct(
        I18n $translator
    ){
        parent::__construct($translator);
    }

    public function translate(PDOException $e): Throwable
    {
        return match ($e->getCode()) {
            '23505' => $this->unique($e),
            '23502' => $this->notNull($e),
            '22001' => $this->valueTooLong($e),
            default => $e,
        };
    }

    private function unique(PDOException $e): Throwable
    {
        $constraint = PdoExtensions::extractConstraintName($e->getMessage());

        if ($constraint === null) {
            return $e;
        }

        $entry = self::UNIQUE[$constraint] ?? null;

        if ($entry === null) {
            return $e;
        }

        return $this->raiseException($entry);
    }

    private function notNull(PDOException $e): Throwable
    {
        // null value in column "code" of relation "languages"
        if (!preg_match('/column "([^"]+)"/', $e->getMessage(), $m)) {
            return $e;
        }

        $column = $m[1];
        $entry  = self::NOT_NULL[$column] ?? null;

        if ($entry === null) {
            return $e;
        }

        return $this->raiseException($entry);
    }

    private function valueTooLong(PDOException $e): Throwable
    {
        // value too long for type character varying(N)
        if (!preg_match('/character varying\((\d+)\)/', $e->getMessage(), $m)) {
            return $e;
        }

        $max = (int) $m[1];

        return new ValidationException([
            'code' => ["Language code must not exceed {$max} characters"],
        ]);
    }
}
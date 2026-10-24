<?php
declare(strict_types=1);

namespace App\Exceptions\Translators;

use App\Exceptions\ValidationException;
use App\Extensions\PdoExtensions;
use App\Exceptions\Translators\Translator as ExceptionTranslator;
use App\Lib\I18n\Translator;
use PDOException;
use Throwable;

final class CategoryExceptionTranslator extends ExceptionTranslator
{
    private const FK = [
        'categories_translations_category_id_fkey' => ['category_id', 'errors.common.not_exists'],
        'categories_translations_code_fkey'        => ['code',        'errors.common.not_exists'],
        'books_category_id_fkey'                   => ['category_id', 'errors.common.not_exists'],
    ];

    private const UNIQUE = [
        'categories_translations_pkey' => ['code', 'errors.common.already_exists'],
    ];

    private const CHECK = [];

    private const NOT_NULL = [
        'category_id' => ['category_id', 'errors.common.required'],
        'code'        => ['code',        'errors.common.required'],
        'title'       => ['title',       'errors.common.required'],
    ];

    public function __construct(
        private readonly Translator $translator
    ){}

    public function translate(PDOException $e): Throwable
    {
        return match ($e->getCode()) {
            '23503' => $this->fk($e),
            '23505' => $this->unique($e),
            '23514' => $this->check($e),
            '23502' => $this->notNull($e),
            '22P02' => $this->invalidEnum($e),
            default => $e,
        };
    }

    private function fk(PDOException $e): Throwable
    {
        $constraint = PdoExtensions::extractConstraintName($e->getMessage());
        if ($constraint === null) return $e;
        $entry = self::FK[$constraint] ?? null;
        return $entry === null ? $e : $this->raiseException($entry);
    }

    private function unique(PDOException $e): Throwable
    {
        $constraint = PdoExtensions::extractConstraintName($e->getMessage());
        if ($constraint === null) return $e;
        $entry = self::UNIQUE[$constraint] ?? null;
        return $entry === null ? $e : $this->raiseException($entry);
    }

    private function check(PDOException $e): Throwable
    {
        $constraint = PdoExtensions::extractConstraintName($e->getMessage());
        if ($constraint === null) return $e;
        $entry = self::CHECK[$constraint] ?? null;
        return $entry === null ? $e : $this->raiseException($entry);
    }

    private function notNull(PDOException $e): Throwable
    {
        if (!preg_match('/column "([^"]+)"/', $e->getMessage(), $m)) return $e;
        $column = $m[1];
        $entry  = self::NOT_NULL[$column] ?? null;
        return $entry === null ? $e : $this->raiseException($entry);
    }
}
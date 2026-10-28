<?php
declare(strict_types=1);

namespace App\Exceptions\Translators;

use App\Exceptions\Translators\Translator as ExceptionTranslator;
use App\Exceptions\ValidationException;
use App\Extensions\PdoExtensions;
use App\Lib\I18n\Translator as I18n;
use PDOException;
use Throwable;

final class RolesExceptionTranslator extends ExceptionTranslator
{
    /** FK: constraint → [поле, сообщение] */
    private const FK = [
        // user_roles_translations
        'user_roles_translations_code_fkey'    => ['code',    'errors.common.not_exists'],
        'user_roles_translations_role_id_fkey' => ['role_id', 'errors.common.not_exists'],
    ];

    /** UNIQUE: constraint → [поле, сообщение] */
    private const UNIQUE = [
        // user_roles
        'user_roles_pkey' => ['id', 'errors.common.already_exists'],
        
        // user_roles_translations (обычно составной PK или UNIQUE на role_id + code)
        'user_roles_translations_pkey' => ['code', 'errors.common.already_exists'],
    ];

    /** NOT NULL: колонка → [поле, сообщение] */
    private const NOT_NULL = [
        // user_roles
        'id'        => ['id',        'errors.common.required'],
        'color'     => ['color',     'errors.common.required'],
        'is_system' => ['is_system', 'errors.common.required'],

        // user_roles_translations
        'role_id' => ['role_id', 'errors.common.required'],
        'code'    => ['code',    'errors.common.required'],
        'title'   => ['title',   'errors.common.required'],
    ];

    public function __construct(
        I18n $translator
    ){
        parent::__construct($translator);
    }

    public function translate(PDOException $e): Throwable
    {
        return match ($e->getCode()) {
            '23503' => $this->fk($e),
            '23505' => $this->unique($e),
            '23502' => $this->notNull($e),
            '22001' => $this->valueTooLong($e),
            default => $e,
        };
    }

    private function fk(PDOException $e): Throwable
    {
        $constraint = PdoExtensions::extractConstraintName($e->getMessage());

        if ($constraint === null) {
            return $e;
        }

        $entry = self::FK[$constraint] ?? null;

        if ($entry === null) {
            return $e;
        }

        return $this->raiseException($entry);
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
        // null value in column "title" of relation "user_roles_translations"
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

        // Assuming title is the most common text field that might overflow
        return new ValidationException([
            'title' => ["Role title must not exceed {$max} characters"],
        ]);
    }
}
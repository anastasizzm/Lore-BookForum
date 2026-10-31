<?php
declare(strict_types=1);

namespace App\Exceptions\Translators;

use App\Exceptions\Translators\Translator as ExceptionTranslator;
use App\Extensions\PdoExtensions;
use App\Lib\I18n\Translator as I18n;
use PDOException;
use Throwable;

final class FileExceptionTranslator extends ExceptionTranslator
{
    /** FK: constraint → [поле, сообщение] */
    private const FK = [
        'files_uploaded_by_fkey' => ['uploaded_by', 'errors.common.not_exists'],
    ];

    /** UNIQUE: constraint → [поле, сообщение] */
    private const UNIQUE = [
        'files_pkey'    => ['id',   'errors.common.already_exists'],
        'files_hash_uq' => ['hash', 'errors.files.duplicate'],
    ];

    /** CHECK: constraint → [поле, сообщение] */
    private const CHECK = [
        'files_size_bytes_check' => ['size_bytes', 'errors.common.min', [':value' => 0]],
    ];

    /** NOT NULL: колонка → [поле, сообщение] */
    private const NOT_NULL = [
        'id'            => ['id',            'errors.common.required'],
        'path'          => ['path',          'errors.common.required'],
        'category'      => ['category',      'errors.common.required'],
        'status'        => ['status',        'errors.common.required'],
        'original_name' => ['original_name', 'errors.common.required'],
        'format'        => ['format',        'errors.common.required'],
        'mime_type'     => ['mime_type',     'errors.common.required'],
        'size_bytes'    => ['size_bytes',    'errors.common.required'],
        'uploaded_by'   => ['uploaded_by',   'errors.common.required'],
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
            '23514' => $this->check($e),
            '23502' => $this->notNull($e),
            default => $e,
        };
    }

    private function fk(PDOException $e): Throwable
    {
        $constraint = PdoExtensions::extractConstraintName($e->getMessage());
        if ($constraint === null) return $e;

        $entry = self::FK[$constraint] ?? null;
        if ($entry === null) return $e;

        return $this->raiseException($entry);
    }

    private function unique(PDOException $e): Throwable
    {
        $constraint = PdoExtensions::extractConstraintName($e->getMessage());
        if ($constraint === null) return $e;

        $entry = self::UNIQUE[$constraint] ?? null;
        if ($entry === null) return $e;

        return $this->raiseException($entry);
    }

    private function check(PDOException $e): Throwable
    {
        $constraint = PdoExtensions::extractConstraintName($e->getMessage());
        if ($constraint === null) return $e;

        $entry = self::CHECK[$constraint] ?? null;
        if ($entry === null) return $e;

        return $this->raiseException($entry);
    }

    private function notNull(PDOException $e): Throwable
    {
        if (!preg_match('/column "([^"]+)"/', $e->getMessage(), $m)) {
            return $e;
        }

        $column = $m[1];
        $entry  = self::NOT_NULL[$column] ?? null;
        if ($entry === null) return $e;

        return $this->raiseException($entry);
    }
}
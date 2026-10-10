<?php
declare(strict_types=1);

namespace App\Exceptions\Translators;

use App\Exceptions\Translators\Translator as ExceptionTranslator;
use App\Exceptions\ValidationException;
use App\Extensions\PdoExtensions;
use App\Lib\I18n\Translator;
use PDOException;
use Throwable;

final class PostExceptionTranslator extends ExceptionTranslator
{
    private const CONTENT_MAX = 2000;

    /** FK: constraint → [поле, сообщение] */
    private const FK = [
        // publications (вставляется перед comments)
        'publications_genre_id_fkey'   => ['genre_id',   'errors.common.not_exists'],
        'publications_icon_id_fkey'    => ['icon_id',    'errors.common.not_exists'],
        'publications_creator_id_fkey' => ['creator_id', 'errors.common.not_exists'],

        // comments
        'comments_publication_id_fkey' => ['publication_id', 'errors.common.not_exists'],
        'comments_creator_id_fkey'     => ['creator_id',     'errors.common.not_exists'],
        'fk_comments_parent'           => [
            'parent_id',
            'errors.common.not_exists',
        ],
    ];

    /** UNIQUE: constraint → [поле, сообщение] */
    private const UNIQUE = [
        'comments_pkey'              => ['id', 'errors.common.already_exists'],
        'uq_comments_id_publication' => ['id', 'errors.common.already_exists'],
    ];

    /** NOT NULL: колонка → [поле, сообщение] */
    private const NOT_NULL = [
        // publications
        'title'        => ['title',        'errors.common.required'],
        'description'  => ['description',  'errors.common.required'],
        'genre_id'     => ['genre_id',     'errors.common.required'],
        'author_notes' => ['author_notes', 'errors.common.required'],

        // comments
        'publication_id' => ['publication_id', 'errors.common.required'],
        'content'        => ['content',        'errors.common.required'],
    ];

    public function __construct(
        private readonly Translator $translator
    ){}

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
        // value too long for type character varying(2000)
        if (!preg_match('/character varying\((\d+)\)/', $e->getMessage(), $m)) {
            return $e;
        }

        $max = (int) $m[1];

        if ($max !== self::CONTENT_MAX) {
            return $e;   // неизвестная колонка — не глотаем
        }

        return new ValidationException([
            'content' => ["Content must not exceed {$max} characters"],
        ]);
    }
}
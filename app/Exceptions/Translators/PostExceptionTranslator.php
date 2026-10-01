<?php
declare(strict_types=1);

namespace App\Exceptions\Translators;

use App\Exceptions\ValidationException;
use App\Extensions\PdoExtensions;
use PDOException;
use Throwable;

final class PostExceptionTranslator
{
    private const CONTENT_MAX = 2000;

    /** FK: constraint → [поле, сообщение] */
    private const FK = [
        // publications (вставляется перед comments)
        'publications_genre_id_fkey'   => ['genre_id',   'The genre does not exist'],
        'publications_icon_id_fkey'    => ['icon_id',    'The icon does not exist'],
        'publications_creator_id_fkey' => ['creator_id', 'The creator does not exist'],

        // comments
        'comments_publication_id_fkey' => ['publication_id', 'The publication does not exist'],
        'comments_creator_id_fkey'     => ['creator_id',     'The user does not exist'],
        'fk_comments_parent'           => [
            'parent_id',
            'The parent comment does not exist or belongs to another publication',
        ],
    ];

    /** UNIQUE: constraint → [поле, сообщение] */
    private const UNIQUE = [
        'comments_pkey'              => ['id', 'A comment with this ID already exists'],
        'uq_comments_id_publication' => ['id', 'A comment with this ID already exists'],
    ];

    /** NOT NULL: колонка → [поле, сообщение] */
    private const NOT_NULL = [
        // publications
        'title'        => ['title',        'Title is required'],
        'description'  => ['description',  'Description is required'],
        'genre_id'     => ['genre_id',     'Genre is required'],
        'author_notes' => ['author_notes', 'Author notes are required'],

        // comments
        'publication_id' => ['publication_id', 'Publication is required'],
        'content'        => ['content',        'Content is required'],
    ];

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

        [$field, $message] = $entry;

        return new ValidationException([$field => [$message]]);
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

        [$field, $message] = $entry;

        return new ValidationException([$field => [$message]]);
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

        [$field, $message] = $entry;

        return new ValidationException([$field => [$message]]);
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
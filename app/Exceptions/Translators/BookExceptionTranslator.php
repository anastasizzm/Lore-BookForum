<?php
declare(strict_types=1);

namespace App\Exceptions\Translators;

use App\Exceptions\ValidationException;
use App\Extensions\PdoExtensions;
use PDOException;
use Throwable;

final class BookExceptionTranslator
{
    /** FK violations: constraint → [поле, сообщение] */
    private const FK = [
        // publications
        'publications_genre_id_fkey'   => ['genre_id',   'The genre does not exist'],
        'publications_icon_id_fkey'    => ['icon_id',    'The icon does not exist'],
        'publications_creator_id_fkey' => ['creator_id', 'The creator does not exist'],

        // books
        'books_publication_id_fkey'    => ['publication_id', 'The publication does not exist'],
        'books_category_id_fkey'       => ['category_id',    'The category does not exist'],
        'books_content_id_fkey'        => ['content_id',     'The content file does not exist'],
    ];

    /** UNIQUE violations */
    private const UNIQUE = [
        'books_publication_id_key' => ['publication_id', 'A book for this publication already exists'],
        'books_isbn_key'           => ['isbn',           'A book with this ISBN already exists'],
    ];

    /** CHECK violations */
    private const CHECK = [
        'books_pages_check' => ['pages', 'Pages must be greater than 0'],
        'books_isbn_check'  => ['isbn',  'ISBN must match the format XXX-X-XXX-XXXXX-X'],
    ];

    /** NOT NULL violations: имя колонки → [поле, сообщение] */
    private const NOT_NULL = [
        // publications
        'title'        => ['title',        'Title is required'],
        'description'  => ['description',  'Description is required'],
        'genre_id'     => ['genre_id',     'Genre is required'],
        'author_notes' => ['author_notes', 'Author notes are required'],

        // books
        'publication_id' => ['publication_id', 'Publication is required'],
        'category_id'    => ['category_id',    'Category is required'],
        'pages'          => ['pages',          'Pages count is required'],
        'content_id'     => ['content_id',     'Content file is required'],
    ];

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

        if ($constraint === null) {
            return $e;
        }

        $entry = self::FK[$constraint] ?? null;

        if ($entry === null) {
            return $e;   // неизвестный FK — не глотаем
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

    private function check(PDOException $e): Throwable
    {
        $constraint = PdoExtensions::extractConstraintName($e->getMessage());

        if ($constraint === null) {
            return $e;
        }

        $entry = self::CHECK[$constraint] ?? null;

        if ($entry === null) {
            return $e;
        }

        [$field, $message] = $entry;

        return new ValidationException([$field => [$message]]);
    }

    private function notNull(PDOException $e): Throwable
    {
        // Сообщение: null value in column "title" of relation "publications"
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
}
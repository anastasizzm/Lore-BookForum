<?php
declare(strict_types=1);

namespace App\Exceptions\Translators;

use App\Exceptions\ValidationException;
use App\Extensions\PdoExtensions;
use PDOException;
use Throwable;

final class ArticleExceptionTranslator
{
    private const ENUM_TYPE = 'article_base';

    /** FK: constraint → [поле, сообщение] */
    private const FK = [
        // publications (вставляется перед articles)
        'publications_genre_id_fkey'   => ['genre_id',   'The genre does not exist'],
        'publications_icon_id_fkey'    => ['icon_id',    'The icon does not exist'],
        'publications_creator_id_fkey' => ['creator_id', 'The creator does not exist'],

        // articles
        'articles_publication_id_fkey' => ['publication_id', 'The publication does not exist'],
        'articles_book_id_fkey'        => ['book_id',        'The book does not exist'],
    ];

    /** UNIQUE: constraint → [поле, сообщение] */
    private const UNIQUE = [
        'articles_publication_id_key' => ['publication_id', 'An article for this publication already exists'],
        'articles_doi_key'            => ['doi',            'An article with this DOI already exists'],
    ];

    /** CHECK: constraint → [поле, сообщение] */
    private const CHECK = [
        'articles_doi_check'          => ['doi',        'DOI must match the format 10.XXXX/YYYY'],
        'articles_page_start_check'   => ['page_start', 'Page start must be greater than 0'],
        'articles_page_end_check'     => ['page_end',   'Page end must be greater than 0'],
        'articles_type_consistency'   => ['type',       'Article fields do not match the selected type'],
    ];

    /** NOT NULL: колонка → [поле, сообщение] */
    private const NOT_NULL = [
        // publications
        'title'        => ['title',        'Title is required'],
        'description'  => ['description',  'Description is required'],
        'genre_id'     => ['genre_id',     'Genre is required'],
        'author_notes' => ['author_notes', 'Author notes are required'],

        // articles
        'publication_id' => ['publication_id', 'Publication is required'],
        'type'           => ['type',           'Type is required'],
    ];

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
        // null value in column "title" of relation "publications"
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

    private function invalidEnum(PDOException $e): Throwable
    {
        // invalid input value for enum article_base: "xyz"
        if (!preg_match('/enum (\w+): "([^"]*)"/', $e->getMessage(), $m)) {
            return $e;
        }

        [, $enumType, $value] = $m;

        if ($enumType !== self::ENUM_TYPE) {
            return $e;
        }

        return new ValidationException([
            'type' => ["'{$value}' is not a valid article type"],
        ]);
    }
}
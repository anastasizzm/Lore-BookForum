<?php
declare(strict_types=1);

namespace App\Exceptions\Translators;

use App\Exceptions\ValidationException;
use App\Extensions\PdoExtensions;
use App\Lib\I18n\Translator;
use PDOException;
use Throwable;

final class ArticleExceptionTranslator extends Translator
{
    /** FK: constraint → [поле, сообщение] */
    private const FK = [
        // publications (вставляется перед articles)
        'publications_genre_id_fkey'   => ['genre_id',   'errors.common.required'],
        'publications_icon_id_fkey'    => ['icon_id',    'errors.common.not_exists'],
        'publications_creator_id_fkey' => ['creator_id', 'errors.common.not_exists'],

        // articles
        'articles_publication_id_fkey' => ['publication_id', 'errors.common.not_exists'],
        'articles_book_id_fkey'        => ['book_id',        'errors.common.not_exists'],
    ];

    /** UNIQUE: constraint → [поле, сообщение] */
    private const UNIQUE = [
        'articles_publication_id_key' => ['publication_id', 'errors.articles.pub_exists'],
        'articles_doi_key'            => ['doi',            'errors.articles.doi_exists'],
    ];

    /** CHECK: constraint → [поле, сообщение] */
    private const CHECK = [
        'articles_doi_check'          => ['doi',        'errors.articles.doi_format'],
        'articles_page_start_check'   => ['page_start', 'errors.common.min', [":value" => 0]],
        'articles_page_end_check'     => ['page_end',   'errors.commin.min', [":value" => 0]],
    ];

    /** NOT NULL: колонка → [поле, сообщение] */
    private const NOT_NULL = [
        // publications
        'title'        => ['title',        'errors.common.required'],
        'description'  => ['description',  'errors.common.required'],
        'genre_id'     => ['genre_id',     'errors.common.required'],
        'type_id'     =>  ['type_id',     'errors.common.required'],
        'author_notes' => ['author_notes', 'errors.common.required'],

        // articles
        'publication_id' => ['publication_id', 'errors.common.required'],
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

        return $this->raiseException($entry);
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

        return $this->raiseException($entry);
    }
}
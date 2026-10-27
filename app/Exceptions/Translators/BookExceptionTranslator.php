<?php
declare(strict_types=1);

namespace App\Exceptions\Translators;

use App\Exceptions\Translators\Translator as ExceptionTranslator;
use App\Exceptions\ValidationException;
use App\Extensions\PdoExtensions;
use App\Lib\I18n\Translator as I18n;
use PDOException;
use Throwable;

final class BookExceptionTranslator extends ExceptionTranslator
{
    private const FK = [
        'publications_genre_id_fkey'   => ['genre_id',   'errors.common.not_exists'],
        'publications_icon_id_fkey'    => ['icon_id',    'errors.common.not_exists'],
        'publications_creator_id_fkey' => ['creator_id', 'errors.common.not_exists'],
        'books_publication_id_fkey'    => ['publication_id', 'errors.common.not_exists'],
        'books_category_id_fkey'       => ['category_id',    'errors.common.not_exists'],
        'books_content_id_fkey'        => ['content_id',     'errors.common.not_exists'],
        'books_content_book_id_fkey'    => ['book_id',    'errors.common.not_exists'],
        'books_content_content_id_fkey' => ['content_id', 'errors.common.not_exists'],
    ];

    private const UNIQUE = [
        'books_publication_id_key' => ['publication_id', 'errors.books.pub_exists'],
        'books_isbn_key'           => ['isbn',           'errors.books.isbn_exists'],
        'books_content_pkey'       => ['format',         'errors.books.format_exists'],
    ];

    private const CHECK = [
        'books_pages_check' => ['pages', 'errors.common.min', [':value' => 0]],
        'books_isbn_check'  => ['isbn',  'errors.books.isbn_format'],
    ];

    private const NOT_NULL = [
        'title'        => ['title',        'errors.common.required'],
        'description'  => ['description',  'errors.common.required'],
        'genre_id'     => ['genre_id',     'errors.common.required'],
        'author_notes' => ['author_notes', 'errors.common.required'],
        'publication_id' => ['publication_id', 'errors.common.required'],
        'category_id'    => ['category_id',    'errors.common.required'],
        'pages'          => ['pages',          'errors.common.required'],
        'content_id'     => ['content_id',     'errors.common.required'],
        'book_id'        => ['book_id',        'errors.common.required'],
        'format'         => ['format',         'errors.common.required'],
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
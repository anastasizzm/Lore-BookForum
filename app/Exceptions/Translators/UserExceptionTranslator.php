<?php
declare(strict_types=1);

namespace App\Exceptions\Translators;

use App\Exceptions\Translators\Translator as ExceptionTranslator;
use App\Exceptions\ValidationException;
use App\Extensions\PdoExtensions;
use App\Lib\I18n\Translator;
use PDOException;
use Throwable;

final class UserExceptionTranslator extends ExceptionTranslator
{
    /** FK: constraint → [поле, сообщение] */
    private const FK = [
        'profiles_user_id_fkey'   => ['user_id', 'errors.common.not_exists'],
        'profiles_icon_id_fkey'   => ['icon_id', 'errors.common.not_exists'],
        'users_rules_user_id_fkey'=> ['user_id', 'errors.common.not_exists'],
    ];

    /** UNIQUE: constraint → [поле, сообщение] */
    private const UNIQUE = [
        'users_pkey'                 => ['id',       'A user with this ID already exists'],
        'uq_users_username_lower'    => ['username', 'This username is already taken'],
        'uq_users_email_lower'       => ['email',    'This email is already registered'],
        'profiles_pkey'              => ['id',       'A profile with this ID already exists'],
        'profiles_user_id_key'       => ['user_id',  'A profile for this user already exists'],
        'users_rules_pkey'           => ['id',       'A rule set with this ID already exists'],
    ];

    /** CHECK: constraint → [поле, сообщение] */
    private const CHECK = [
        'users_username_check' => [
            'username',
            'errors.users.username_format',
        ],
    ];

    /** NOT NULL: колонка → [поле, сообщение] */
    private const NOT_NULL = [
        // users
        'username'  => ['username',  'errors.common.required'],
        'email'     => ['email',     'errors.common.required'],
        'pass_hash' => ['pass_hash', 'errors.common.required'],

        // profiles
        'name'    => ['name',    'errors.common.required'],
        'surname' => ['surname', 'errors.common.required'],
        'icon_id' => ['icon_id', 'errors.common.required'],

        // users_rules
        'user_id' => ['user_id', 'errors.common.required'],
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
        // null value in column "username" of relation "users"
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
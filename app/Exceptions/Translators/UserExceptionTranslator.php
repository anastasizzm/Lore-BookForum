<?php
declare(strict_types=1);

namespace App\Exceptions\Translators;

use App\Exceptions\ValidationException;
use App\Extensions\PdoExtensions;
use PDOException;
use Throwable;

final class UserExceptionTranslator
{
    /** FK: constraint → [поле, сообщение] */
    private const FK = [
        'profiles_user_id_fkey'   => ['user_id', 'The user does not exist'],
        'profiles_icon_id_fkey'   => ['icon_id', 'The icon file does not exist'],
        'users_rules_user_id_fkey'=> ['user_id', 'The user does not exist'],
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
            'Username must be 3–30 characters and contain only letters, digits, and underscores',
        ],
    ];

    /** NOT NULL: колонка → [поле, сообщение] */
    private const NOT_NULL = [
        // users
        'username'  => ['username',  'Username is required'],
        'email'     => ['email',     'Email is required'],
        'pass_hash' => ['pass_hash', 'Password is required'],

        // profiles
        'name'    => ['name',    'Name is required'],
        'surname' => ['surname', 'Surname is required'],
        'icon_id' => ['icon_id', 'Icon is required'],

        // users_rules
        'user_id' => ['user_id', 'User is required'],
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
        // null value in column "username" of relation "users"
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
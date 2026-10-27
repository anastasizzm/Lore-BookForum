<?php
declare(strict_types=1);

namespace App\Exceptions\Translators;

use App\Exceptions\ValidationException;
use App\Extensions\PdoExtensions;
use App\Exceptions\Translators\Translator as ExceptionTranslator;
use App\Lib\I18n\Translator as I18n;
use PDOException;
use Throwable;

final class PermissionExceptionTranslator extends ExceptionTranslator
{
    private const FK_NOT_EXISTS = [
        'roles_permissions_role_id_fkey'        => ['role_id',        'errors.common.not_exists'],
        'roles_permissions_publication_id_fkey' => ['publication_id', 'errors.common.not_exists'],
        'roles_users_user_id_fkey'              => ['user_id',        'errors.common.not_exists'],
        'roles_users_publication_id_fkey'       => ['publication_id', 'errors.common.not_exists'],
        'roles_users_granted_by_id_fkey'        => ['granted_by_id',  'errors.common.not_exists'],
        'roles_users_publication_id_role_id_fkey' => ['role_id', 'errors.common.not_found'],
    ];

    private const FK_IN_USE = [
        'roles_users_publication_id_role_id_fkey' => ['role_id', 'errors.common.in_use'],
    ];

    private const UNIQUE = [
        'roles_permissions_pub_role_uq'      => ['role_id',        'errors.common.already_exists'],
        'roles_permissions_pub_lexorank_uq'  => ['lexorank',       'errors.common.already_exists'],
        'roles_users_user_pub_role_valid_uq' => ['role_id',        'errors.common.already_exists'],
        'roles_users_valid_uq'               => ['role_id',        'errors.common.already_exists'],
    ];

    private const NOT_NULL = [
        'role_id'        => ['role_id',        'errors.common.required'],
        'lexorank'       => ['lexorank',       'errors.common.required'],
        'user_id'        => ['user_id',        'errors.common.required'],
        'granted_by_id'  => ['granted_by_id',  'errors.common.required'],
        'valid_from'     => ['valid_from',     'errors.common.required'],
    ];

    public function __construct(
        I18n $translator
    ) {
        parent::__construct($translator);
    }

    public function translate(PDOException $e): Throwable
    {
        return match ($e->getCode()) {
            '23503' => $this->fk($e),
            '23505' => $this->unique($e),
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

        if (isset(self::FK_IN_USE[$constraint])
            && str_contains($e->getMessage(), 'is still referenced')) {
            return $this->raiseException(self::FK_IN_USE[$constraint]);
        }

        $entry = self::FK_NOT_EXISTS[$constraint] ?? null;

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
}
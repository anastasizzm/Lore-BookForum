<?php
declare(strict_types=1);

namespace App\Exceptions\Translators;

use App\Exceptions\ValidationException;
use App\Extensions\PdoExtensions;
use App\Exceptions\Translators\Translator as ExceptionTranslator;
use App\Lib\I18n\Translator;
use PDOException;
use Throwable;

final class PermissionExceptionTranslator extends ExceptionTranslator
{
    /**
     * FK insert-side: inserting into a child table, referenced row missing.
     * Constraint is defined on the child, fires when the parent lookup fails.
     */
    private const FK_NOT_EXISTS = [
        // INSERT INTO roles_permissions
        'roles_permissions_role_id_fkey'        => ['role_id',        'errors.common.not_exists'],
        'roles_permissions_publication_id_fkey' => ['publication_id', 'errors.common.not_exists'],

        // INSERT INTO roles_users
        'roles_users_user_id_fkey'              => ['user_id',        'errors.common.not_exists'],
        'roles_users_publication_id_fkey'       => ['publication_id', 'errors.common.not_exists'],
        'roles_users_granted_by_id_fkey'        => ['granted_by_id',  'errors.common.not_exists'],

        // No (publication_id, role_id) row in roles_permissions →
        // the caller tried to assign a role that isn't defined for that publication.
        'roles_users_publication_id_role_id_fkey' => ['role_id', 'errors.common.not_found'],
    ];

    /**
     * FK delete-side: deleting from roles_permissions while roles_users
     * still references the row (ON DELETE RESTRICT).
     * Constraint name lives on roles_users, fires when deleting the parent.
     */
    private const FK_IN_USE = [
        'roles_users_publication_id_role_id_fkey' => ['role_id', 'errors.common.in_use'],
    ];

    /** UNIQUE: constraint → [поле, сообщение] */
    private const UNIQUE = [
        // One permission set per (publication, role) — re-inserting the same pair.
        'roles_permissions_pub_role_uq'      => ['role_id',        'errors.common.already_exists'],
        // Rank collision within a scope (deferred — fires at COMMIT).
        'roles_permissions_pub_rank_uq'      => ['authority_rank', 'errors.common.in_use'],
        // Same user, same publication, same role, same valid_from.
        'roles_users_user_pub_role_valid_uq' => ['role_id',        'errors.common.already_exists'],
    ];

    /**
     * NOT NULL: column → [поле, сообщение]
     * publication_id excluded — nullable in both tables after migration.
     */
    private const NOT_NULL = [
        // roles_permissions
        'role_id'        => ['role_id',        'errors.common.required'],
        'authority_rank' => ['authority_rank', 'errors.common.required'],

        // roles_users
        'user_id'        => ['user_id',        'errors.common.required'],
        'granted_by_id'  => ['granted_by_id',  'errors.common.required'],
        'valid_from'     => ['valid_from',     'errors.common.required'],
    ];

    public function __construct(
        private readonly Translator $translator
    ) {}

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

        // Direction detection: RESTRICT produces "is still referenced from table"
        // in the DETAIL clause; the NOT_EXISTS case produces "is not present
        // in table". Same SQLSTATE, same constraint name, different fix.
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
        // null value in column "role_id" of relation "roles_permissions"
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
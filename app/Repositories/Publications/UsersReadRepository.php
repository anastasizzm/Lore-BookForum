<?php
declare(strict_types=1);

namespace App\Repositories\Publications;

use App\Lib\Data\Database;
use App\Lib\I18n\Translator;
use App\Models\Enums\BookFormat;
use App\Models\Publications\UserRead;
use App\Models\Scripts\ScriptParam;
use App\Repositories\Repository;
use PDO;

final class UsersReadRepository extends Repository
{
    public function __construct(
        Database $db,
        protected Translator $translator,
    ){
        parent::__construct($db);
    }

    public function find(
        int        $userId,
        int        $publicationId,
        BookFormat $format,
    ): ?UserRead {
        $sql = 'SELECT user_id, publication_id, format, position, is_finished, last_read_at
             FROM users_read
             WHERE user_id = :user_id
               AND publication_id = :publication_id
               AND format = :format
             LIMIT 1';
        
        $params = [
            ':user_id'        => ScriptParam::asInt($userId),
            ':publication_id' => ScriptParam::asInt($publicationId),
            ':format'         => ScriptParam::asStr($format->value),
        ];

        $stmt = $this->execute($sql, $params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : UserRead::fromRow($row);
    }

    public function exists(int $userId, int $publicationId): bool
    {
        $sql = 'SELECT 1 FROM users_read
             WHERE user_id = :user_id AND publication_id = :publication_id
             LIMIT 1';

        $params = [
            ':user_id'        => ScriptParam::asInt($userId),
            ':publication_id' =>  ScriptParam::asInt($publicationId),
        ];

        $stmt = $this->execute($sql, $params);
        return $stmt->fetchColumn() !== false;
    }

    // ==================== Write ====================

    public function upsert(
        int        $userId,
        int        $publicationId,
        BookFormat $format,
        array      $position,
        bool       $isFinished = false,
    ): void {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO users_read
                (user_id, publication_id, format, position, is_finished, last_read_at)
             VALUES
                (:user_id, :publication_id, :format, :position::jsonb, :is_finished, NOW())
             ON CONFLICT (user_id, publication_id, format) DO UPDATE
             SET
                position     = EXCLUDED.position,
                is_finished  = EXCLUDED.is_finished OR users_read.is_finished,
                last_read_at = NOW()'
        );

        $stmt->execute([
            ':user_id'        => $userId,
            ':publication_id' => $publicationId,
            ':format'         => $format->value,
            ':position'       => json_encode($position, JSON_THROW_ON_ERROR),
            ':is_finished'    => $isFinished ? "1" : "0",
        ]);
    }

    public function markFinished(
        int        $userId,
        int        $publicationId,
        BookFormat $format,
    ): void {
        $stmt = $this->pdo()->prepare(
            'UPDATE users_read
             SET is_finished = TRUE, last_read_at = NOW()
             WHERE user_id = :user_id
               AND publication_id = :publication_id
               AND format = :format'
        );

        $stmt->execute([
            ':user_id'        => $userId,
            ':publication_id' => $publicationId,
            ':format'         => $format->value,
        ]);
    }

    public function unmarkFinished(
        int        $userId,
        int        $publicationId,
        BookFormat $format,
    ): void {
        $stmt = $this->pdo()->prepare(
            'UPDATE users_read
             SET is_finished = FALSE, last_read_at = NOW()
             WHERE user_id = :user_id
               AND publication_id = :publication_id
               AND format = :format'
        );

        $stmt->execute([
            ':user_id'        => $userId,
            ':publication_id' => $publicationId,
            ':format'         => $format->value,
        ]);
    }

    public function delete(int $userId, int $publicationId): bool
    {
        $stmt = $this->pdo()->prepare(
            'DELETE FROM users_read
             WHERE user_id = :user_id AND publication_id = :publication_id'
        );

        $stmt->execute([
            ':user_id'        => $userId,
            ':publication_id' => $publicationId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function deleteForFormat(
        int        $userId,
        int        $publicationId,
        BookFormat $format,
    ): bool {
        $stmt = $this->pdo()->prepare(
            'DELETE FROM users_read
             WHERE user_id = :user_id
               AND publication_id = :publication_id
               AND format = :format'
        );

        $stmt->execute([
            ':user_id'        => $userId,
            ':publication_id' => $publicationId,
            ':format'         => $format->value,
        ]);

        return $stmt->rowCount() > 0;
    }
}
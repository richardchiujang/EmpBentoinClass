<?php
namespace App\Models;

class Notification
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(int $request_id, string $message, string $target_role): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO notifications (request_id, message, target_role)
             VALUES (:request_id, :message, :target_role)
             RETURNING id"
        );
        $stmt->execute([
            ':request_id'  => $request_id,
            ':message'     => $message,
            ':target_role' => $target_role,
        ]);
        return (int)$stmt->fetchColumn();
    }

    public function findForRole(string $role, bool $unread_only = false): array
    {
        $sql = 'SELECT * FROM notifications WHERE target_role = :role';
        if ($unread_only) {
            $sql .= ' AND read_flag = FALSE';
        }
        $sql .= ' ORDER BY created_at DESC LIMIT 50';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':role' => $role]);
        return $stmt->fetchAll();
    }

    public function markRead(int $id): void
    {
        $this->pdo->prepare('UPDATE notifications SET read_flag = TRUE WHERE id = :id')
                  ->execute([':id' => $id]);
    }
}

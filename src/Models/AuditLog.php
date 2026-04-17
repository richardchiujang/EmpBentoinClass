<?php
namespace App\Models;

class AuditLog
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Write one audit log entry inside a transaction.
     */
    public function create(int $request_id, string $stage, string $operator, ?string $comment = null): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO audit_logs (request_id, stage, operator, comment)
             VALUES (:request_id, :stage, :operator, :comment)
             RETURNING id"
        );
        $stmt->execute([
            ':request_id' => $request_id,
            ':stage'      => $stage,
            ':operator'   => $operator,
            ':comment'    => $comment,
        ]);
        return (int)$stmt->fetchColumn();
    }

    public function findByRequest(int $request_id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM audit_logs WHERE request_id = :id ORDER BY action_date'
        );
        $stmt->execute([':id' => $request_id]);
        return $stmt->fetchAll();
    }
}

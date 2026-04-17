<?php
namespace App\Models;

class WorkflowLog
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(string $order_no, int $sequence_no, string $status_code, ?int $handler_id, ?string $opinion = null): int
    {
        $sql = 'INSERT INTO workflow_logs (order_no, sequence_no, action_date, status_code, handler_id, opinion) VALUES (:order_no, :sequence_no, NOW(), :status_code, :handler_id, :opinion)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':order_no' => $order_no,
            ':sequence_no' => $sequence_no,
            ':status_code' => $status_code,
            ':handler_id' => $handler_id,
            ':opinion' => $opinion,
        ]);

        return (int)$this->pdo->lastInsertId();
    }
}

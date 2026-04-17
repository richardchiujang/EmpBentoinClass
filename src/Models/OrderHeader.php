<?php
namespace App\Models;

class OrderHeader
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM order_headers ORDER BY created_at DESC');
        return $stmt->fetchAll();
    }

    /** Get a single order with its details and workflow logs */
    public function getWithDetails(string $order_no): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM order_headers WHERE order_no = :no');
        $stmt->execute([':no' => $order_no]);
        $header = $stmt->fetch();
        if (!$header) return null;

        $d = $this->pdo->prepare('SELECT * FROM order_details WHERE order_no = :no ORDER BY detail_id');
        $d->execute([':no' => $order_no]);
        $header['details'] = $d->fetchAll();

        $w = $this->pdo->prepare('SELECT wl.*, u.full_name FROM workflow_logs wl LEFT JOIN users u ON wl.handler_id = u.user_id WHERE wl.order_no = :no ORDER BY wl.sequence_no');
        $w->execute([':no' => $order_no]);
        $header['workflow'] = $w->fetchAll();

        return $header;
    }

    /** List orders filtered by status_code (or all if null) */
    public function findByStatus(?string $status_code): array
    {
        if ($status_code === null) {
            return $this->all();
        }
        $stmt = $this->pdo->prepare('SELECT * FROM order_headers WHERE status_code = :s ORDER BY created_at DESC');
        $stmt->execute([':s' => $status_code]);
        return $stmt->fetchAll();
    }

    /** List orders for a specific applicant */
    public function findByApplicant(int $applicant_id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM order_headers WHERE applicant_id = :id ORDER BY created_at DESC');
        $stmt->execute([':id' => $applicant_id]);
        return $stmt->fetchAll();
    }

    public function create(array $data): string
    {
        $order_no = 'S' . date('Ymd') . substr(uniqid(), -6);

        $sql = 'INSERT INTO order_headers (order_no, applicant_id, dept_code, apply_date, meal_date, meal_time, meal_type, location, purpose, budget_code, total_amount, status_code, current_handler_id, remarks) VALUES (:order_no, :applicant_id, :dept_code, :apply_date, :meal_date, :meal_time, :meal_type, :location, :purpose, :budget_code, :total_amount, :status_code, :current_handler_id, :remarks)';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':order_no' => $order_no,
            ':applicant_id' => $data['applicant_id'] ?? null,
            ':dept_code' => $data['dept_code'] ?? null,
            ':apply_date' => $data['apply_date'] ?? date('Y-m-d'),
            ':meal_date' => $data['meal_date'] ?? date('Y-m-d'),
            ':meal_time' => $data['meal_time'] ?? '08:00:00',
            ':meal_type' => $data['meal_type'] ?? null,
            ':location' => $data['location'] ?? null,
            ':purpose' => $data['purpose'] ?? null,
            ':budget_code' => $data['budget_code'] ?? null,
            ':total_amount' => $data['total_amount'] ?? 0,
            ':status_code' => $data['status_code'] ?? '1',
            ':current_handler_id' => $data['current_handler_id'] ?? null,
            ':remarks' => $data['remarks'] ?? null,
        ]);

        return $order_no;
    }
}

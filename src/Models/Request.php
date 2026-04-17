<?php
namespace App\Models;

class Request
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Return all requests visible to the given user/role (data isolation per spec 4.5).
     */
    public function findForUser(string $username, string $role, ?string $status = null): array
    {
        $where = '';
        $params = [];

        switch ($role) {
            case 'admin':
                $where = '1=1';
                break;

            case 'staff':
                // Only own requests
                $where = 'u.username = :username';
                $params[':username'] = $username;
                break;

            case 'manager':
                // Own + pending their approval + subordinates'
                $where = '(u.username = :username OR r.next_operator = :username OR u.manager_username = :username)';
                $params[':username'] = $username;
                break;

            case 'restaurant':
            case 'finance':
                // Only requests at their approval step (or already passed)
                $where = 'r.next_operator = :username';
                $params[':username'] = $username;
                break;

            default:
                return [];
        }

        if ($status !== null && $status !== '') {
            $where .= ' AND r.status = :status';
            $params[':status'] = $status;
        }

        $sql = "SELECT r.*, u.display_name AS applicant_display, un.unit_name,
                       COALESCE(SUM(oi.quantity * COALESCE(oi.custom_price, m.price)), 0) AS total_amount
                FROM requests r
                LEFT JOIN users u  ON r.applicant_id = u.id
                LEFT JOIN units un ON r.unit_id = un.id
                LEFT JOIN order_items oi ON oi.request_id = r.id
                LEFT JOIN meals m ON m.id = oi.meal_id
                WHERE {$where}
                GROUP BY r.id, u.display_name, un.unit_name
                ORDER BY r.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Return a single request with order_items and audit_logs.
     */
    public function getWithDetails(string $request_no): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.*, u.display_name AS applicant_display, un.unit_name
             FROM requests r
             LEFT JOIN users u  ON r.applicant_id = u.id
             LEFT JOIN units un ON r.unit_id = un.id
             WHERE r.request_no = :no"
        );
        $stmt->execute([':no' => $request_no]);
        $row = $stmt->fetch();
        if (!$row) return null;

        // Order items with meal info
        $items = $this->pdo->prepare(
            "SELECT oi.*, m.name AS meal_name, m.category,
                    COALESCE(oi.custom_price, m.price) AS unit_price,
                    oi.quantity * COALESCE(oi.custom_price, m.price) AS subtotal
             FROM order_items oi
             LEFT JOIN meals m ON m.id = oi.meal_id
             WHERE oi.request_id = :id
             ORDER BY oi.id"
        );
        $items->execute([':id' => $row['id']]);
        $row['order_items'] = $items->fetchAll();

        // Audit logs
        $logs = $this->pdo->prepare(
            'SELECT * FROM audit_logs WHERE request_id = :id ORDER BY action_date'
        );
        $logs->execute([':id' => $row['id']]);
        $row['audit_logs'] = $logs->fetchAll();

        return $row;
    }

    /**
     * Create a new request and return its request_no.
     * Always starts with next_operator = 'manager01' (first approval step).
     */
    public function create(array $data, int $applicant_id): array
    {
        $year_suffix = date('y');
        $seq = (int)$this->pdo->query("SELECT nextval('request_no_seq')")->fetchColumn();
        $request_no = 'S' . $year_suffix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);

        $sql = "INSERT INTO requests
                    (request_no, status, applicant_id, applicant_name, extension,
                     unit_id, budget_source, meal_date, meal_type, meal_time,
                     meal_location, meal_reason, notes, next_operator)
                VALUES
                    (:request_no, 'submitted', :applicant_id, :applicant_name, :extension,
                     :unit_id, :budget_source, :meal_date, :meal_type, :meal_time,
                     :meal_location, :meal_reason, :notes, 'manager01')
                RETURNING id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':request_no'    => $request_no,
            ':applicant_id'  => $applicant_id,
            ':applicant_name'=> $data['applicant_name'] ?? '',
            ':extension'     => $data['extension'] ?? null,
            ':unit_id'       => $data['unit_id'] ?? null,
            ':budget_source' => $data['budget_source'] ?? null,
            ':meal_date'     => $data['meal_date'] ?? date('Y-m-d'),
            ':meal_type'     => $data['meal_type'] ?? null,
            ':meal_time'     => $data['meal_time'] ?? '08:00:00',
            ':meal_location' => $data['meal_location'] ?? null,
            ':meal_reason'   => $data['meal_reason'] ?? null,
            ':notes'         => $data['notes'] ?? null,
        ]);
        $id = (int)$stmt->fetchColumn();

        return ['id' => $id, 'request_no' => $request_no];
    }

    /**
     * Update status and next_operator (used by WorkflowController inside a transaction).
     */
    public function updateStatus(int $id, string $status, ?string $next_operator): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE requests SET status = :status, next_operator = :next_op WHERE id = :id'
        );
        $stmt->execute([':status' => $status, ':next_op' => $next_operator, ':id' => $id]);
    }

    /**
     * Fetch minimal row by request_no (for workflow checks).
     */
    public function findByNo(string $request_no): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM requests WHERE request_no = :no');
        $stmt->execute([':no' => $request_no]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}

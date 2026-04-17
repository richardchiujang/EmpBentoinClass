<?php
namespace App\Controllers;

use App\Models\WorkflowLog;

class WorkflowController
{
    private \PDO $pdo;

    // Valid status transitions: current_status => allowed_next_statuses
    private const TRANSITIONS = [
        '1' => ['2', 'X'],      // 申請中 → 審核中 or 銷案
        '2' => ['3', 'X'],      // 審核中 → 已核決 or 銷案
        '3' => ['4', 'X'],      // 已核決 → 用膳中 or 銷案
        '4' => ['5'],           // 用膳中 → 待付款
        '5' => ['6'],           // 待付款 → 已付款
        '6' => [],              // 已付款 (terminal)
        'X' => [],              // 已銷案 (terminal)
    ];

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** POST /workflow/action — advance an order's status */
    public function action(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input) || empty($input['order_no']) || empty($input['status_code'])) {
            http_response_code(400);
            echo json_encode(['error' => 'order_no 與 status_code 為必填'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $order_no   = $input['order_no'];
        $new_status = $input['status_code'];
        $handler_id = $input['handler_id'] ?? null;
        $opinion    = $input['opinion'] ?? null;

        // Fetch current status
        $cur = $this->pdo->prepare('SELECT status_code FROM order_headers WHERE order_no = :no');
        $cur->execute([':no' => $order_no]);
        $row = $cur->fetch();
        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => '申請單不存在'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $current = $row['status_code'];
        $allowed = self::TRANSITIONS[$current] ?? [];
        if (!in_array($new_status, $allowed, true)) {
            http_response_code(422);
            echo json_encode([
                'error'   => "狀態轉換不合法：{$current} → {$new_status}",
                'allowed' => $allowed,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $this->pdo->beginTransaction();

            $seqStmt = $this->pdo->prepare('SELECT COALESCE(MAX(sequence_no),0)+1 AS next_seq FROM workflow_logs WHERE order_no = :no');
            $seqStmt->execute([':no' => $order_no]);
            $next = (int)$seqStmt->fetchColumn();

            $wl = new WorkflowLog($this->pdo);
            $wl->create($order_no, $next, $new_status, $handler_id, $opinion);

            $upd = $this->pdo->prepare('UPDATE order_headers SET status_code = :status, current_handler_id = :handler WHERE order_no = :no');
            $upd->execute([':status' => $new_status, ':handler' => $handler_id, ':no' => $order_no]);

            $this->pdo->commit();
            echo json_encode(['ok' => true, 'order_no' => $order_no, 'new_status' => $new_status], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /** GET /workflow/logs?order_no= — retrieve workflow history */
    public function logs(): void
    {
        $order_no = $_GET['order_no'] ?? '';
        if ($order_no === '') {
            http_response_code(400);
            echo json_encode(['error' => 'order_no 為必填'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $stmt = $this->pdo->prepare(
            'SELECT wl.*, u.full_name FROM workflow_logs wl
             LEFT JOIN users u ON wl.handler_id = u.user_id
             WHERE wl.order_no = :no ORDER BY wl.sequence_no'
        );
        $stmt->execute([':no' => $order_no]);
        echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
    }
}

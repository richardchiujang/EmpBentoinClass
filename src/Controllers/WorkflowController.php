<?php
namespace App\Controllers;

use App\Models\Request;
use App\Models\AuditLog;
use App\Models\Notification;

class WorkflowController
{
    private \PDO $pdo;

    /**
     * Valid status transitions per spec 4.2 / 4.3.
     * Terminal states: completed, rejected, cancelled.
     */
    private const TRANSITIONS = [
        'draft'     => ['submitted', 'cancelled'],
        'submitted' => ['reviewing', 'rejected', 'cancelled'],
        'reviewing' => ['reviewing', 'approved', 'rejected'],
        'approved'  => ['completed'],
        'completed' => [],
        'rejected'  => [],
        'cancelled' => [],
    ];

    /** Fixed approval chain (step_order 1→2→3) per spec 4.4 */
    private const APPROVAL_CHAIN = ['manager01', 'restaurant01', 'finance01'];

    private const STATUS_LABELS = [
        'draft'     => '填寫中',
        'submitted' => '已送案',
        'reviewing' => '審核中',
        'approved'  => '已核決',
        'completed' => '已用膳',
        'rejected'  => '已退回',
        'cancelled' => '已銷案',
    ];

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * POST /workflow/action
     * Body: { request_no, new_status, comment }
     *
     * When new_status = 'reviewing', the backend auto-determines whether
     * the next operator exists; if not, it promotes the request to 'approved'.
     */
    public function action(): void
    {
        $user  = getCurrentUser();
        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input) || empty($input['request_no']) || empty($input['new_status'])) {
            http_response_code(400);
            echo json_encode(['error' => 'request_no 與 new_status 為必填'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $request_no = $input['request_no'];
        $new_status = $input['new_status'];
        $comment    = trim($input['comment'] ?? '');

        // Rejection requires a comment
        if ($new_status === 'rejected' && $comment === '') {
            http_response_code(400);
            echo json_encode(['error' => '退回必須填寫意見'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $request = (new Request($this->pdo))->findByNo($request_no);
        if (!$request) {
            http_response_code(404);
            echo json_encode(['error' => '申請單不存在'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $current = $request['status'];
        $allowed = self::TRANSITIONS[$current] ?? [];

        if (!in_array($new_status, $allowed, true)) {
            http_response_code(422);
            echo json_encode([
                'error'   => "狀態轉換不合法：{$current} → {$new_status}",
                'allowed' => $allowed,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Permission check (skip for admin)
        if ($user['role'] !== 'admin') {
            $this->checkPermission($user, $request, $new_status);
        }

        // Determine actual_status and next_operator when approving
        $actual_status = $new_status;
        $next_operator = null;

        if ($new_status === 'reviewing') {
            $next_operator = $this->getNextOperator($request['next_operator']);
            if ($next_operator === null) {
                // End of chain — auto-promote
                $actual_status = 'approved';
            }
        }

        try {
            $this->pdo->beginTransaction();

            (new Request($this->pdo))->updateStatus($request['id'], $actual_status, $next_operator);

            $stage_label = self::STATUS_LABELS[$actual_status] ?? $actual_status;
            (new AuditLog($this->pdo))->create(
                $request['id'],
                $stage_label,
                $user['display_name'],
                $comment ?: null
            );

            $this->sendNotifications(
                new Notification($this->pdo),
                $request,
                $actual_status,
                $next_operator
            );

            $this->pdo->commit();

            echo json_encode([
                'ok'            => true,
                'request_no'    => $request_no,
                'new_status'    => $actual_status,
                'next_operator' => $next_operator,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /** GET /workflow/logs?request_no= */
    public function logs(): void
    {
        $request_no = $_GET['request_no'] ?? '';
        if ($request_no === '') {
            http_response_code(400);
            echo json_encode(['error' => 'request_no 為必填'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $stmt = $this->pdo->prepare(
            'SELECT al.*
             FROM audit_logs al
             INNER JOIN requests r ON al.request_id = r.id
             WHERE r.request_no = :no
             ORDER BY al.action_date'
        );
        $stmt->execute([':no' => $request_no]);
        echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /**
     * Enforce role-based action permissions per spec 4.6.
     * Aborts with 403 if the current user lacks permission.
     */
    private function checkPermission(array $user, array $request, string $new_status): void
    {
        $role     = $user['role'];
        $username = $user['username'];

        // Applicant (staff/manager) can cancel own requests or submit drafts
        if (in_array($role, ['staff', 'manager'], true)) {
            if (in_array($new_status, ['cancelled', 'submitted'], true)) {
                return; // allow
            }
            // Manager can mark approved requests as completed
            if ($role === 'manager' && $new_status === 'completed') {
                return;
            }
        }

        // Approvers can only act when they are the next_operator
        if (in_array($role, ['manager', 'restaurant', 'finance'], true)) {
            if ($request['next_operator'] !== $username) {
                http_response_code(403);
                echo json_encode(['error' => '無此簽核權限'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            return;
        }

        http_response_code(403);
        echo json_encode(['error' => '操作不允許'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Return the next username in the approval chain after $current_operator,
     * or null if $current_operator is the last step.
     */
    private function getNextOperator(?string $current_operator): ?string
    {
        if ($current_operator === null) {
            return self::APPROVAL_CHAIN[0] ?? null;
        }
        $idx = array_search($current_operator, self::APPROVAL_CHAIN, true);
        if ($idx === false) {
            return self::APPROVAL_CHAIN[0] ?? null;
        }
        $next = $idx + 1;
        return $next < count(self::APPROVAL_CHAIN) ? self::APPROVAL_CHAIN[$next] : null;
    }

    /**
     * Write notifications per spec 4.7 routing rules.
     */
    private function sendNotifications(
        Notification $notif,
        array $request,
        string $new_status,
        ?string $next_operator
    ): void {
        $no = $request['request_no'];
        $id = $request['id'];

        switch ($new_status) {
            case 'submitted':
                $notif->create($id, "單號 {$no} 待您審核", 'manager');
                break;

            case 'reviewing':
                if ($next_operator !== null) {
                    $stmt = $this->pdo->prepare('SELECT role FROM users WHERE username = :u');
                    $stmt->execute([':u' => $next_operator]);
                    $next_role = $stmt->fetchColumn() ?: 'manager';
                    $notif->create($id, "單號 {$no} 已流轉至您的簽核關卡", $next_role);
                }
                break;

            case 'approved':
                $notif->create($id, "單號 {$no} 已核准", 'staff');
                $notif->create($id, "單號 {$no} 已核准", 'manager');
                break;

            case 'rejected':
                $notif->create($id, "單號 {$no} 已退回，請查看意見", 'staff');
                break;

            case 'cancelled':
                $notif->create($id, "單號 {$no} 已由申請人撤銷", 'manager');
                break;

            case 'completed':
                $notif->create($id, "單號 {$no} 已完成用膳", 'staff');
                $notif->create($id, "單號 {$no} 已完成用膳", 'finance');
                break;
        }
    }
}

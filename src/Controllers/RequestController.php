<?php
namespace App\Controllers;

use App\Models\Request;
use App\Models\OrderItem;
use App\Models\AuditLog;
use App\Models\Notification;

class RequestController
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** GET /requests[?status=] — list requests visible to current user */
    public function list(): void
    {
        $user   = getCurrentUser();
        $status = $_GET['status'] ?? null;

        $rows = (new Request($this->pdo))->findForUser(
            $user['username'],
            $user['role'],
            ($status !== '' ? $status : null)
        );
        echo json_encode($rows, JSON_UNESCAPED_UNICODE);
    }

    /** GET /requests/{request_no} */
    public function show(string $request_no): void
    {
        $data = (new Request($this->pdo))->getWithDetails($request_no);
        if ($data === null) {
            http_response_code(404);
            echo json_encode(['error' => '申請單不存在'], JSON_UNESCAPED_UNICODE);
            return;
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /** POST /requests — create new request */
    public function create(): void
    {
        $user  = getCurrentUser();
        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Required fields
        foreach (['applicant_name', 'meal_date', 'meal_time'] as $field) {
            if (empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "缺少必填欄位: {$field}"], JSON_UNESCAPED_UNICODE);
                return;
            }
        }

        $items = $input['order_items'] ?? [];
        if (empty($items)) {
            http_response_code(400);
            echo json_encode(['error' => '至少需要一筆餐點明細'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $this->pdo->beginTransaction();

            $requestModel = new Request($this->pdo);
            $result       = $requestModel->create($input, (int)$user['id']);

            $itemModel = new OrderItem($this->pdo);
            foreach ($items as $item) {
                $itemModel->create($result['id'], $item);
            }

            // Initial audit log
            (new AuditLog($this->pdo))->create(
                $result['id'],
                '已送案',
                $user['display_name'],
                '送案給主管審核'
            );

            // Notify first approver
            (new Notification($this->pdo))->create(
                $result['id'],
                "單號 {$result['request_no']} 待您審核",
                'manager'
            );

            $this->pdo->commit();

            http_response_code(201);
            echo json_encode(
                ['request_no' => $result['request_no'], 'message' => '申請單建立成功'],
                JSON_UNESCAPED_UNICODE
            );
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }
}

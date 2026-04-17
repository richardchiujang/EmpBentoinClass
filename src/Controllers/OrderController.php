<?php
namespace App\Controllers;

use App\Models\OrderHeader;
use App\Models\OrderDetail;

class OrderController
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** GET /orders[?status=&applicant_id=] */
    public function list(): void
    {
        $model = new OrderHeader($this->pdo);
        $status = isset($_GET['status']) ? $_GET['status'] : null;
        $applicant = isset($_GET['applicant_id']) ? (int)$_GET['applicant_id'] : null;

        if ($applicant !== null) {
            $rows = $model->findByApplicant($applicant);
        } else {
            $rows = $model->findByStatus($status);
        }
        echo json_encode($rows, JSON_UNESCAPED_UNICODE);
    }

    /** GET /orders/{order_no} */
    public function show(string $order_no): void
    {
        $model = new OrderHeader($this->pdo);
        $data = $model->getWithDetails($order_no);
        if ($data === null) {
            http_response_code(404);
            echo json_encode(['error' => '申請單不存在'], JSON_UNESCAPED_UNICODE);
            return;
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /** POST /orders */
    public function create(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Required field validation
        foreach (['applicant_id', 'meal_date', 'meal_time'] as $field) {
            if (empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "缺少必填欄位: $field"], JSON_UNESCAPED_UNICODE);
                return;
            }
        }

        try {
            $this->pdo->beginTransaction();
            $oh = new OrderHeader($this->pdo);
            $order_no = $oh->create($input);

            $od = new OrderDetail($this->pdo);
            foreach ($input['details'] ?? [] as $detail) {
                $od->create($order_no, $detail);
            }

            $this->pdo->commit();
            http_response_code(201);
            echo json_encode(['order_no' => $order_no, 'message' => '申請單建立成功'], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }
}

<?php
namespace App\Controllers;

class ReportController
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** GET /report/monthly?year=&month= — budget summary by budget_source */
    public function monthlyBudgetSummary(): void
    {
        $year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
        $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');

        $sql = "SELECT r.budget_source,
                       COUNT(DISTINCT r.id) AS request_count,
                       COALESCE(SUM(oi.quantity * COALESCE(oi.custom_price, m.price)), 0) AS total_amount
                FROM requests r
                LEFT JOIN order_items oi ON oi.request_id = r.id
                LEFT JOIN meals m ON m.id = oi.meal_id
                WHERE EXTRACT(YEAR  FROM r.meal_date) = :y
                  AND EXTRACT(MONTH FROM r.meal_date) = :m
                  AND r.status NOT IN ('cancelled', 'rejected')
                GROUP BY r.budget_source
                ORDER BY total_amount DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':y' => $year, ':m' => $month]);
        echo json_encode(
            ['year' => $year, 'month' => $month, 'data' => $stmt->fetchAll()],
            JSON_UNESCAPED_UNICODE
        );
    }

    /** GET /report/daily?date= — daily delivery list for meal provider */
    public function dailyDelivery(): void
    {
        $date = $_GET['date'] ?? date('Y-m-d');

        $sql = "SELECT r.request_no, r.meal_time, r.meal_type, r.meal_location,
                       r.meal_reason, r.applicant_name, un.unit_name, r.status,
                       COALESCE(SUM(oi.quantity * COALESCE(oi.custom_price, m.price)), 0) AS total_amount,
                       json_agg(json_build_object(
                           'meal_name',      m.name,
                           'category',       m.category,
                           'quantity',       oi.quantity,
                           'unit_price',     COALESCE(oi.custom_price, m.price),
                           'payment_method', oi.payment_method,
                           'subtotal',       oi.quantity * COALESCE(oi.custom_price, m.price)
                       ) ORDER BY oi.id) AS items
                FROM requests r
                LEFT JOIN units un      ON r.unit_id    = un.id
                LEFT JOIN order_items oi ON oi.request_id = r.id
                LEFT JOIN meals m        ON m.id          = oi.meal_id
                WHERE r.meal_date = :date
                  AND r.status NOT IN ('draft', 'cancelled', 'rejected')
                GROUP BY r.request_no, r.meal_time, r.meal_type, r.meal_location,
                         r.meal_reason, r.applicant_name, un.unit_name, r.status
                ORDER BY r.meal_time";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':date' => $date]);
        echo json_encode(
            ['date' => $date, 'orders' => $stmt->fetchAll()],
            JSON_UNESCAPED_UNICODE
        );
    }
}


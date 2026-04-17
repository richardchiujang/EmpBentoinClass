<?php
namespace App\Controllers;

class ReportController
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** GET /report/monthly?year=&month= — monthly budget summary */
    public function monthlyBudgetSummary(): void
    {
        $year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
        $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');

        $sql = 'SELECT oh.budget_code, b.subject_name,
                       SUM(oh.total_amount) AS total_amount,
                       COUNT(*) AS orders_count
                FROM order_headers oh
                LEFT JOIN budgets b ON oh.budget_code = b.budget_code
                WHERE EXTRACT(YEAR  FROM oh.meal_date) = :y
                  AND EXTRACT(MONTH FROM oh.meal_date) = :m
                GROUP BY oh.budget_code, b.subject_name
                ORDER BY total_amount DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':y' => $year, ':m' => $month]);
        echo json_encode(['year' => $year, 'month' => $month, 'data' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
    }

    /** GET /report/daily?date= — daily delivery list for meal provider */
    public function dailyDelivery(): void
    {
        $date = $_GET['date'] ?? date('Y-m-d');
        $sql = 'SELECT oh.order_no, oh.meal_time, oh.meal_type, oh.location,
                       oh.purpose, u.full_name AS applicant_name, d.dept_name,
                       oh.total_amount, oh.status_code,
                       json_agg(json_build_object(
                           \'item_id\', od.item_id,
                           \'quantity\', od.quantity,
                           \'price_per_unit\', od.price_per_unit,
                           \'payment_method\', od.payment_method,
                           \'subtotal\', od.subtotal
                       ) ORDER BY od.detail_id) AS details
                FROM order_headers oh
                LEFT JOIN users u ON oh.applicant_id = u.user_id
                LEFT JOIN departments d ON oh.dept_code = d.dept_code
                LEFT JOIN order_details od ON oh.order_no = od.order_no
                WHERE oh.meal_date = :date
                GROUP BY oh.order_no, oh.meal_time, oh.meal_type, oh.location,
                         oh.purpose, u.full_name, d.dept_name, oh.total_amount, oh.status_code
                ORDER BY oh.meal_time';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':date' => $date]);
        echo json_encode(['date' => $date, 'orders' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
    }
}

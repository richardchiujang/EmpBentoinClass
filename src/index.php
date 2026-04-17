<?php
declare(strict_types=1);

$pdo = require __DIR__ . '/bootstrap.php';

use App\Controllers\OrderController;
use App\Controllers\AuthController;
use App\Controllers\WorkflowController;
use App\Controllers\ReportController;
use App\Models\MealItem;
use App\Models\Budget;
use App\Models\User;
use App\Models\Department;

$method = $_SERVER['REQUEST_METHOD'];
$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path   = rtrim($path, '/') ?: '/';

// ── Helpers ───────────────────────────────────────────────────────────────────
function jsonResponse(mixed $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
}

function htmlPage(string $file): void
{
    $full = __DIR__ . '/Templates/' . $file;
    if (!file_exists($full)) {
        jsonResponse(['error' => 'Template not found'], 404);
        return;
    }
    header('Content-Type: text/html; charset=utf-8');
    include $full;
}

// Extract last path segment for resource IDs e.g. /orders/S920005
function lastSegment(string $path): string
{
    return basename($path);
}

// ════════════════════════════════════════════════════════════════════════════
// ROUTING
// ════════════════════════════════════════════════════════════════════════════

// ── HTML Pages ────────────────────────────────────────────────────────────────
if ($path === '/' && $method === 'GET') {
    htmlPage('order_form.php');
    exit;
}

// ── Orders API ────────────────────────────────────────────────────────────────
$orderCtrl = new OrderController($pdo);

if ($path === '/orders' && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    $orderCtrl->list();
    exit;
}

if ($path === '/orders' && $method === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $orderCtrl->create();
    exit;
}

// GET /orders/{order_no}
if (preg_match('#^/orders/([A-Za-z0-9]+)$#', $path, $m) && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    $orderCtrl->show($m[1]);
    exit;
}

// ── Auth ──────────────────────────────────────────────────────────────────────
$authCtrl = new AuthController($pdo);

if ($path === '/auth/login' && $method === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $authCtrl->login();
    exit;
}

// ── Workflow ──────────────────────────────────────────────────────────────────
$workflowCtrl = new WorkflowController($pdo);

if ($path === '/workflow/action' && $method === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $workflowCtrl->action();
    exit;
}

if ($path === '/workflow/logs' && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    $workflowCtrl->logs();
    exit;
}

// ── Reports ───────────────────────────────────────────────────────────────────
$reportCtrl = new ReportController($pdo);

if ($path === '/report/monthly' && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    $reportCtrl->monthlyBudgetSummary();
    exit;
}

if ($path === '/report/daily' && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    $reportCtrl->dailyDelivery();
    exit;
}

// ── Master data (for dropdowns) ───────────────────────────────────────────────
if ($path === '/api/meal-items' && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode((new MealItem($pdo))->all(), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($path === '/api/budgets' && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode((new Budget($pdo))->all(), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($path === '/api/users' && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode((new User($pdo))->all(), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($path === '/api/departments' && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode((new Department($pdo))->all(), JSON_UNESCAPED_UNICODE);
    exit;
}

// ── 404 ───────────────────────────────────────────────────────────────────────
jsonResponse([
    'error' => 'Not found',
    'path'  => $path,
    'available_routes' => [
        'GET  /'                  => '訂餐申請表單 (HTML)',
        'GET  /orders'            => '查詢申請單列表 [?status=&applicant_id=]',
        'POST /orders'            => '建立新申請單',
        'GET  /orders/{order_no}' => '查詢單一申請單（含明細與歷程）',
        'POST /auth/login'        => '使用者登入',
        'POST /workflow/action'   => '簽核動作',
        'GET  /workflow/logs'     => '查詢簽核歷程 ?order_no=',
        'GET  /report/monthly'    => '月度預算報表 [?year=&month=]',
        'GET  /report/daily'      => '每日配送清單 [?date=YYYY-MM-DD]',
        'GET  /api/meal-items'    => '餐點項目主檔',
        'GET  /api/budgets'       => '預算科目主檔',
        'GET  /api/users'         => '使用者清單',
        'GET  /api/departments'   => '單位清單',
    ],
], 404);


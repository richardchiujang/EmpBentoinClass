<?php
declare(strict_types=1);

$pdo = require __DIR__ . '/bootstrap.php';

use App\Controllers\AuthController;
use App\Controllers\RequestController;
use App\Controllers\WorkflowController;
use App\Controllers\ReportController;
use App\Models\Meal;
use App\Models\Unit;
use App\Models\User;
use App\Models\Notification;

$method = $_SERVER['REQUEST_METHOD'];
$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path   = rtrim($path, '/') ?: '/';

// ── Helper ────────────────────────────────────────────────────────────────────
function jsonResponse(mixed $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
}

// ════════════════════════════════════════════════════════════════════════════
// ROUTING
// ════════════════════════════════════════════════════════════════════════════

$authCtrl = new AuthController($pdo);

// ── Public auth routes ────────────────────────────────────────────────────────
if ($path === '/login' && $method === 'GET') {
    $authCtrl->showLogin();
    exit;
}

if ($path === '/login' && $method === 'POST') {
    $authCtrl->login();
    exit;
}

if ($path === '/logout' && $method === 'GET') {
    $authCtrl->logout();
    exit;
}

// ── Protected HTML page ───────────────────────────────────────────────────────
if ($path === '/' && $method === 'GET') {
    requireLogin();
    header('Content-Type: text/html; charset=utf-8');
    include __DIR__ . '/Templates/order_form.php';
    exit;
}

// ── All routes below require login (JSON) ────────────────────────────────────
requireLoginJson();

$requestCtrl  = new RequestController($pdo);
$workflowCtrl = new WorkflowController($pdo);
$reportCtrl   = new ReportController($pdo);

// ── Requests API ──────────────────────────────────────────────────────────────
if ($path === '/requests' && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    $requestCtrl->list();
    exit;
}

if ($path === '/requests' && $method === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $requestCtrl->create();
    exit;
}

if (preg_match('#^/requests/([A-Za-z0-9]+)$#', $path, $m) && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    $requestCtrl->show($m[1]);
    exit;
}

// ── Workflow API ──────────────────────────────────────────────────────────────
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

// ── Reports API ───────────────────────────────────────────────────────────────
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

// ── Master data (dropdowns) ───────────────────────────────────────────────────
if ($path === '/api/meals' && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode((new Meal($pdo))->all(), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($path === '/api/units' && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode((new Unit($pdo))->all(), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($path === '/api/users' && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode((new User($pdo))->all(), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($path === '/api/notifications' && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    $role = getCurrentRole() ?? '';
    echo json_encode((new Notification($pdo))->findForRole($role), JSON_UNESCAPED_UNICODE);
    exit;
}

// ── 404 ───────────────────────────────────────────────────────────────────────
jsonResponse([
    'error' => 'Not found',
    'path'  => $path,
    'available_routes' => [
        'GET  /'                    => '主頁 SPA（需登入）',
        'GET  /login'               => '登入頁',
        'POST /login'               => '登入動作',
        'GET  /logout'              => '登出',
        'GET  /requests'            => '申請單列表 [?status=]',
        'POST /requests'            => '建立申請單',
        'GET  /requests/{no}'       => '查詢單一申請單（含明細與歷程）',
        'POST /workflow/action'     => '簽核動作 {request_no, new_status, comment}',
        'GET  /workflow/logs'       => '簽辦歷程 [?request_no=]',
        'GET  /report/monthly'      => '月報 [?year=&month=]',
        'GET  /report/daily'        => '日報 [?date=YYYY-MM-DD]',
        'GET  /api/meals'           => '餐點主檔',
        'GET  /api/units'           => '單位主檔',
        'GET  /api/users'           => '使用者清單',
        'GET  /api/notifications'   => '通知列表',
    ],
], 404);


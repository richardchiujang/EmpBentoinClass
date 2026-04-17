<?php
// Bootstrap: start session, load autoload, provide auth helpers, create PDO
session_start();

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => 'Missing Composer autoload',
        'hint' => 'Run `composer install` in the project root to generate vendor/autoload.php'
    ]);
    exit;
}

require $autoload;

// ── Auth helper functions (spec 7.3) ──────────────────────────────────────────

function getCurrentUser(): ?array
{
    return $_SESSION['auth_user'] ?? null;
}

function getCurrentRole(): ?string
{
    return $_SESSION['role'] ?? null;
}

/** Redirect to /login for HTML routes if not authenticated. */
function requireLogin(): void
{
    if (empty($_SESSION['username'])) {
        header('Location: /login');
        exit;
    }
}

/** Return 401 JSON for API routes if not authenticated. */
function requireLoginJson(): void
{
    if (empty($_SESSION['username'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => '未登入', 'redirect' => '/login']);
        exit;
    }
}

// ── DB connection ─────────────────────────────────────────────────────────────
$pdo = null;
try {
    $pdo = (new \App\Config\DB())->getConnection();
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'DB connection failed', 'message' => $e->getMessage()]);
    exit;
}

return $pdo;

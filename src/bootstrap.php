<?php
// Bootstrap: load composer autoload and create PDO connection
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

// Lazy-load DB connection
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

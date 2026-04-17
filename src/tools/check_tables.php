<?php
$pdo = require __DIR__ . '/../bootstrap.php';

$info = $pdo->query("SELECT current_database() AS db, current_user AS usr, current_schema() AS schema, current_setting('search_path') AS search_path")->fetch();
echo "database: " . ($info['db'] ?? 'n/a') . PHP_EOL;
echo "user: " . ($info['usr'] ?? 'n/a') . PHP_EOL;
echo "schema: " . ($info['schema'] ?? 'n/a') . PHP_EOL;
echo "search_path: " . ($info['search_path'] ?? 'n/a') . PHP_EOL;
echo PHP_EOL;

$stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public' ORDER BY table_name");
$rows = $stmt->fetchAll();
if (empty($rows)) {
    echo "(no tables found in public schema)" . PHP_EOL;
} else {
    foreach ($rows as $r) {
        echo $r['table_name'] . PHP_EOL;
    }
}

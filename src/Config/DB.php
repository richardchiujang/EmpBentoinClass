<?php
namespace App\Config;

class DB
{
    private \PDO $pdo;

    public function __construct()
    {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '5432';
        $db   = getenv('DB_NAME') ?: 'tongxin_meal';
        $user = getenv('DB_USER') ?: 'postgres';
        $pass = getenv('DB_PASS') ?: '1234';

        $dsn = "pgsql:host={$host};port={$port};dbname={$db}";

        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ];

        $this->pdo = new \PDO($dsn, $user, $pass, $options);
    }

    public function getConnection(): \PDO
    {
        return $this->pdo;
    }
}

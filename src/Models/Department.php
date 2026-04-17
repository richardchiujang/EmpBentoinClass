<?php
namespace App\Models;

class Department
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM departments ORDER BY dept_code');
        return $stmt->fetchAll();
    }

    public function find(string $code): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM departments WHERE dept_code = :code');
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}

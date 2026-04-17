<?php
namespace App\Models;

class Budget
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM budgets ORDER BY budget_code');
        return $stmt->fetchAll();
    }

    public function getBalance(string $budget_code): ?float
    {
        $stmt = $this->pdo->prepare('SELECT balance FROM budgets WHERE budget_code = :code');
        $stmt->execute([':code' => $budget_code]);
        $row = $stmt->fetch();
        return $row ? (float)$row['balance'] : null;
    }

    public function deduct(string $budget_code, float $amount): bool
    {
        $sql = 'UPDATE budgets SET balance = balance - :amt WHERE budget_code = :code AND balance >= :amt';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':amt' => $amount, ':code' => $budget_code]);
    }
}

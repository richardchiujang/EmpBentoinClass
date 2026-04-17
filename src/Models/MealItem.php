<?php
namespace App\Models;

class MealItem
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM meal_items WHERE item_id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM meal_items ORDER BY item_id');
        return $stmt->fetchAll();
    }
}

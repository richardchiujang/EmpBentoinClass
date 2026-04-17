<?php
namespace App\Models;

class Unit
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(): array
    {
        return $this->pdo->query(
            'SELECT * FROM units ORDER BY id'
        )->fetchAll();
    }
}

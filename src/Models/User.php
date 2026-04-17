<?php
namespace App\Models;

class User
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(): array
    {
        return $this->pdo->query(
            'SELECT id, username, display_name, role, unit_id, manager_username
             FROM users WHERE is_active = TRUE ORDER BY id'
        )->fetchAll();
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM users WHERE username = :u AND is_active = TRUE'
        );
        $stmt->execute([':u' => $username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}


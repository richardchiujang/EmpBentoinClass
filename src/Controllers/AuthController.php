<?php
namespace App\Controllers;

class AuthController
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function login(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input) || empty($input['username'])) {
            http_response_code(400);
            echo json_encode(['error' => 'username required']);
            return;
        }

        $stmt = $this->pdo->prepare('SELECT user_id, username, full_name, dept_code, role FROM users WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $input['username']]);
        $user = $stmt->fetch();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'invalid credentials']);
            return;
        }

        // For demo: return user info (no password handling). Integrate real auth in production.
        echo json_encode($user);
    }
}

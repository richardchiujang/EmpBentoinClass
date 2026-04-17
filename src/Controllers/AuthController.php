<?php
namespace App\Controllers;

use App\Models\User;

class AuthController
{
    private \PDO $pdo;

    // All demo accounts share this password (see spec 7.1)
    private const DEMO_PASSWORD = '1234';

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** GET /login — display login page */
    public function showLogin(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        include __DIR__ . '/../Templates/login.php';
    }

    /** POST /login — validate credentials, write session */
    public function login(): void
    {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password !== self::DEMO_PASSWORD) {
            $error = '帳號或密碼錯誤';
            include __DIR__ . '/../Templates/login.php';
            return;
        }

        $user = (new User($this->pdo))->findByUsername($username);
        if (!$user) {
            $error = '帳號不存在或已停用';
            include __DIR__ . '/../Templates/login.php';
            return;
        }

        // Store user info in session
        $_SESSION['username']     = $user['username'];
        $_SESSION['display_name'] = $user['display_name'];
        $_SESSION['role']         = $user['role'];
        $_SESSION['user_id']      = $user['id'];
        $_SESSION['auth_user']    = $user;

        header('Location: /');
        exit;
    }

    /** GET /logout */
    public function logout(): void
    {
        session_destroy();
        header('Location: /login');
        exit;
    }
}


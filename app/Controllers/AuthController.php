<?php

namespace App\Controllers;

require_once __DIR__ . '/../../includes/db.php';

class AuthController
{
    public function showLogin(): void
    {
        $this->renderLogin();
    }

    public function login(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        global $pdo;

        $stmt = $pdo->prepare('SELECT * FROM Benutzer WHERE Email = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['Passwort'])) {
            $_SESSION['user_role'] = 'admin';
            $_SESSION['user_id'] = $user['BenutzerID'];
            header('Location: /dashboard.php');
            exit;
        }

        $stmt = $pdo->prepare('SELECT * FROM Fahrer WHERE Fahrernummer = ? AND Code = ?');
        $stmt->execute([$username, $password]);
        $driver = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($driver) {
            $_SESSION['user_role'] = 'fahrer';
            $_SESSION['user_id'] = $driver['FahrerID'];
            header('Location: /driver/dashboard.php');
            exit;
        }

        $this->renderLogin('Ungültige Anmeldedaten!');
    }

    private function renderLogin(?string $error = null): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $title = 'Login';
        $showNav = false;
        $bodyClass = 'login-page';

        include __DIR__ . '/../../includes/layout.php';
        include __DIR__ . '/../Views/auth/login.php';
        echo '</body></html>';
    }
}

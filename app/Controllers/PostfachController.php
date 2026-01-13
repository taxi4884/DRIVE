<?php
namespace App\Controllers;

use App\Models\Message;

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/cache.php';
require_once __DIR__ . '/../../includes/user_check.php';

class PostfachController
{
    private const CACHE_TTL = 900;
    private const PERMISSIONS_CACHE_KEY = 'message_permissions_matrix';
    private const USERS_CACHE_KEY = 'benutzer_all';

    private function ensureAuthenticated(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }
    }

    private function loadPermissions(): array
    {
        global $pdo;

        $permStmt = $pdo->query('SELECT driver_id, recipient_id FROM message_permissions');
        $result = [];
        while ($row = $permStmt->fetch(\PDO::FETCH_ASSOC)) {
            $driverId = (int) $row['driver_id'];
            $recipientId = (int) $row['recipient_id'];
            $result[$driverId][] = $recipientId;
        }

        return $result;
    }

    private function loadUsers(): array
    {
        global $pdo;

        $usersStmt = $pdo->query('SELECT BenutzerID, Name FROM Benutzer ORDER BY Name');
        return $usersStmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getRecipients(int $userId, bool $isDriver): array
    {
        $allUsers = Cache::remember(self::USERS_CACHE_KEY, [$this, 'loadUsers'], self::CACHE_TTL);

        if (!$isDriver) {
            return $allUsers;
        }

        $permissions = Cache::remember(self::PERMISSIONS_CACHE_KEY, [$this, 'loadPermissions'], self::CACHE_TTL);

        $allowedRecipients = $permissions[$userId] ?? [];
        return array_values(array_filter($allUsers, static function (array $user) use ($allowedRecipients) {
            return in_array((int) $user['BenutzerID'], $allowedRecipients, true);
        }));
    }

    public function inbox(): void
    {
        $this->ensureAuthenticated();

        $userId = (int) $_SESSION['user_id'];
        $isDriver = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'fahrer';
        $recipients = $this->getRecipients($userId, $isDriver);

        $conversations = Message::getConversationsByUser($userId);
        $conversation = [];
        if (isset($_GET['with'])) {
            $otherId = (int) $_GET['with'];
            Message::markConversationAsRead($userId, $otherId);
            $conversation = Message::getMessagesBetween($userId, $otherId);
        }
        $success = ($_GET['success'] ?? '') !== '';
        $title = 'Postfach';
        $currentUserId = $userId;

        include __DIR__ . '/../../includes/layout.php';
        include __DIR__ . '/../Views/messages/inbox.php';
        echo "</body></html>";
    }

    public function compose(): void
    {
        $this->ensureAuthenticated();

        $userId = (int) $_SESSION['user_id'];
        $isDriver = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'fahrer';
        $recipients = $this->getRecipients($userId, $isDriver);

        $title = 'Neue Nachricht';
        include __DIR__ . '/../../includes/layout.php';
        include __DIR__ . '/../Views/messages/compose.php';
        echo "</body></html>";
    }

    public function store(): void
    {
        $this->ensureAuthenticated();

        $senderId = (int) $_SESSION['user_id'];
        $recipientId = (int) ($_POST['recipient_id'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $body = trim($_POST['body'] ?? '');

        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
        $isAjax = stripos($acceptHeader, 'application/json') !== false;
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            $isAjax = true;
        }

        $error = null;
        if ($recipientId === 0 || $subject === '' || $body === '') {
            $error = 'Empfänger, Betreff und Nachricht dürfen nicht leer sein.';
        }

        $isDriver = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'fahrer';
        if ($isDriver) {
            $permissions = Cache::remember(self::PERMISSIONS_CACHE_KEY, [$this, 'loadPermissions'], self::CACHE_TTL);

            $allowedRecipients = $permissions[$senderId] ?? [];
            if (!in_array($recipientId, $allowedRecipients, true)) {
                $error = 'Sie dürfen diesem Empfänger keine Nachricht senden.';
            }
        }

        if ($error !== null) {
            if ($isAjax) {
                http_response_code(422);
                header('Content-Type: application/json');
                echo json_encode(['error' => $error]);
                exit;
            }

            header('Location: /postfach/compose');
            exit;
        }

        global $pdo;
        $stmt = $pdo->prepare('INSERT INTO messages (sender_id, recipient_id, subject, body) VALUES (?, ?, ?, ?)');
        $stmt->execute([$senderId, $recipientId, $subject, $body]);

        if ($isAjax) {
            $messageId = (int) $pdo->lastInsertId();
            $message = Message::findWithSender($messageId);

            header('Content-Type: application/json');
            echo json_encode($message ?? ['success' => true]);
            exit;
        }

        header('Location: /postfach?success=1');
        exit;
    }
}

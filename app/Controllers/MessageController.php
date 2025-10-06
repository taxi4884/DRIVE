<?php
namespace App\Controllers;

use App\Models\Message;

require_once __DIR__ . '/../../includes/db.php';

class MessageController
{
    public function index(): void
    {
        session_start();
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login.php');
            exit;
        }

        $userId = (int) $_SESSION['user_id'];
        $conversations = Message::getConversationsByUser($userId);
        $title = 'Nachrichten';
        $extraCss = 'css/messages.css';
        include __DIR__ . '/../../includes/layout.php';
        include __DIR__ . '/../Views/messages/index.php';
        echo "</body></html>";
    }

    public function show(int $otherId): void
    {
        session_start();
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login.php');
            exit;
        }

        $userId = (int) $_SESSION['user_id'];
        Message::markConversationAsRead($userId, $otherId);
        $messages = Message::getMessagesBetween($userId, $otherId);
        $title = 'Nachrichtenverlauf';
        $extraCss = 'css/messages.css';
        include __DIR__ . '/../../includes/layout.php';
        include __DIR__ . '/../Views/messages/show.php';
        echo "</body></html>";
    }

    public function inbox(): void
    {
        session_start();
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login.php');
            exit;
        }

        $userId = (int) $_SESSION['user_id'];
        $conversations = Message::getConversationsByUser($userId);
        $conversation = [];
        if (isset($_GET['with'])) {
            $otherId = (int) $_GET['with'];
            Message::markConversationAsRead($userId, $otherId);
            $conversation = Message::getMessagesBetween($userId, $otherId);
        }
        $success = ($_GET['success'] ?? '') !== '';
        $title = 'Postfach';
        $extraCss = 'css/messages.css';
        $currentUserId = $userId;
        include __DIR__ . '/../../includes/layout.php';
        include __DIR__ . '/../Views/messages/inbox.php';
        echo "</body></html>";
    }

    public function markAsRead(): void
    {
        session_start();
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login.php');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
            $messageId = (int) $_POST['id'];
            $userId = (int) $_SESSION['user_id'];
            Message::markAsRead($messageId, $userId);
        }

        header('Location: /messages');
        exit;
    }

    public function store(): void
    {
        session_start();
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Nicht angemeldet']);
            return;
        }

        $senderId = (int) $_SESSION['user_id'];
        $recipientId = (int) ($_POST['recipient_id'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $body = trim($_POST['body'] ?? '');

        if ($recipientId === 0 || $subject === '' || $body === '') {
            http_response_code(422);
            echo json_encode(['error' => 'Ungültige Eingabe']);
            return;
        }

        global $pdo;

        $isDriver = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'fahrer';
        if ($isDriver) {
            $check = $pdo->prepare('SELECT 1 FROM message_permissions WHERE driver_id = ? AND recipient_id = ?');
            $check->execute([$senderId, $recipientId]);
            if (!$check->fetchColumn()) {
                http_response_code(403);
                echo json_encode(['error' => 'Empfänger nicht erlaubt']);
                return;
            }
        }

        $stmt = $pdo->prepare('INSERT INTO messages (sender_id, recipient_id, subject, body) VALUES (?, ?, ?, ?)');
        $stmt->execute([$senderId, $recipientId, $subject, $body]);

        echo json_encode(['status' => 'ok']);
    }
}

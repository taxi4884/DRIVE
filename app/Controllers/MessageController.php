<?php
namespace App\Controllers;

use App\Models\Message;

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
        include __DIR__ . '/../Views/messages/index.php';
    }

    public function show(int $otherId): void
    {
        session_start();
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login.php');
            exit;
        }

        $userId = (int) $_SESSION['user_id'];
        $messages = Message::getMessagesBetween($userId, $otherId);
        include __DIR__ . '/../Views/messages/show.php';
    }

    public function inbox(): void
    {
        session_start();
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login.php');
            exit;
        }

        $userId = (int) $_SESSION['user_id'];
        $messages = Message::getUnreadByUser($userId);
        include __DIR__ . '/../Views/messages/inbox.php';
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
}

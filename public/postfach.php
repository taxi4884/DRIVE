<?php
session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../app/Models/Message.php';

use App\Models\Message;

function showInbox(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }

    $userId = (int) $_SESSION['user_id'];
    $messages = Message::getUnreadByUser($userId);
    include __DIR__ . '/../app/Views/messages/inbox.php';
}

function saveMessage(): void
{
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

$action = $_GET['action'] ?? '';

if ($action === 'store') {
    saveMessage();
} else {
    showInbox();
}

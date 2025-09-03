<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/user_check.php';
require_once __DIR__ . '/../app/Models/Message.php';

use App\Models\Message;

function showInbox(): void
{
    global $sekundarRolle;

    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }

    $userId = (int) $_SESSION['user_id'];
    $messages = Message::getUnreadByUser($userId);
    $success = ($_GET['success'] ?? '') !== '';
    include __DIR__ . '/../app/Views/messages/inbox.php';
}

function showCompose(): void
{
    global $sekundarRolle;

    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }

    include __DIR__ . '/../app/Views/messages/compose.php';
}

function saveMessage(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }

    $senderId = (int) $_SESSION['user_id'];
    $recipientId = (int) ($_POST['recipient_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');

    if ($recipientId === 0 || $subject === '' || $body === '') {
        header('Location: /postfach.php?action=compose');
        exit;
    }

    global $pdo;

    $isDriver = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'fahrer';
    if ($isDriver) {
        $check = $pdo->prepare('SELECT 1 FROM message_permissions WHERE driver_id = ? AND recipient_id = ?');
        $check->execute([$senderId, $recipientId]);
        if (!$check->fetchColumn()) {
            header('Location: /postfach.php?action=compose');
            exit;
        }
    }

    $stmt = $pdo->prepare('INSERT INTO messages (sender_id, recipient_id, subject, body) VALUES (?, ?, ?, ?)');
    $stmt->execute([$senderId, $recipientId, $subject, $body]);

    header('Location: /postfach.php?success=1');
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'store') {
    saveMessage();
} elseif ($action === 'compose') {
    showCompose();
} else {
    showInbox();
}

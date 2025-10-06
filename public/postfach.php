<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/user_check.php';
require_once __DIR__ . '/../app/Models/Message.php';

use App\Models\Message;

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'store') {
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

$userId = (int) $_SESSION['user_id'];
global $pdo;

$isDriver = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'fahrer';
if ($isDriver) {
    $stmt = $pdo->prepare('SELECT b.BenutzerID, b.Name FROM Benutzer b JOIN message_permissions mp ON b.BenutzerID = mp.recipient_id WHERE mp.driver_id = ? ORDER BY b.Name');
    $stmt->execute([$userId]);
} else {
    $stmt = $pdo->query('SELECT BenutzerID, Name FROM Benutzer ORDER BY Name');
}

$recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

$extraCss = 'css/messages.css';
$currentUserId = $userId;

if ($action === 'compose') {
    $title = 'Neue Nachricht';
    $view = __DIR__ . '/../app/Views/messages/compose.php';
} else {
    $title = 'Postfach';
    $conversations = Message::getConversationsByUser($userId);
    $conversation = [];
    if (isset($_GET['with'])) {
        $otherId = (int) $_GET['with'];
        Message::markConversationAsRead($userId, $otherId);
        $conversation = Message::getMessagesBetween($userId, $otherId);
    }
    $success = ($_GET['success'] ?? '') !== '';
    $view = __DIR__ . '/../app/Views/messages/inbox.php';
}

include __DIR__ . '/../includes/layout.php';
include $view;

?>
</body>
</html>


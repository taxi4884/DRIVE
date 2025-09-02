<?php
require_once '../../includes/bootstrap.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht angemeldet']);
    exit;
}

$senderId = (int) $_SESSION['user_id'];
$recipientId = (int) ($_POST['recipient_id'] ?? 0);
$subject = trim($_POST['subject'] ?? '');
$body = trim($_POST['body'] ?? '');

if ($recipientId === 0 || $subject === '' || $body === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Ungültige Eingabe']);
    exit;
}

if (isDriver()) {
    $check = $pdo->prepare('SELECT 1 FROM message_permissions WHERE driver_id = ? AND recipient_id = ?');
    $check->execute([$senderId, $recipientId]);
    if (!$check->fetchColumn()) {
        http_response_code(403);
        echo json_encode(['error' => 'Empfänger nicht erlaubt']);
        exit;
    }
}

$stmt = $pdo->prepare('INSERT INTO messages (sender_id, recipient_id, subject, body) VALUES (?, ?, ?, ?)');
$stmt->execute([$senderId, $recipientId, $subject, $body]);

echo json_encode(['status' => 'ok']);

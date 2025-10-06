<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../app/Models/Message.php';

use App\Models\Message;

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthenticated']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$messages = Message::getUnreadByUser($userId);

echo json_encode(array_map(static function (array $message): array {
    return [
        'id' => (int) $message['id'],
        'subject' => $message['subject'],
        'body' => $message['body'],
        'created_at' => $message['created_at'],
        'sender_name' => $message['sender_name'],
    ];
}, $messages));

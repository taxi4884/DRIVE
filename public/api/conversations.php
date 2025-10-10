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
$conversations = Message::getConversationsByUser($userId);

echo json_encode(array_map(static function (array $conversation): array {
    return [
        'id' => (int) $conversation['id'],
        'subject' => $conversation['subject'],
        'body' => $conversation['body'],
        'created_at' => $conversation['created_at'],
        'other_id' => (int) $conversation['other_id'],
        'other_name' => $conversation['other_name'],
        'unread_count' => (int) ($conversation['unread_count'] ?? 0),
    ];
}, $conversations));

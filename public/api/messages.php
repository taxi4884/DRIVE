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

$otherId = (int) ($_GET['other_id'] ?? 0);
if ($otherId === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing other_id']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
Message::markConversationAsRead($userId, $otherId);
$messages = Message::getMessagesBetween($userId, $otherId);
echo json_encode($messages);


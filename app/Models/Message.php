<?php
namespace App\Models;

use PDO;

require_once __DIR__ . '/../../includes/db.php';

class Message
{
    public static function getUnreadByUser(int $userId): array
    {
        global $pdo;
        $stmt = $pdo->prepare('
            SELECT m.id, m.subject, m.body, m.created_at, b.Name AS sender_name
            FROM messages m
            JOIN Benutzer b ON m.sender_id = b.BenutzerID
            WHERE m.recipient_id = ? AND m.read_at IS NULL
            ORDER BY m.created_at DESC
        ');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function markAsRead(int $messageId, int $userId): bool
    {
        global $pdo;
        $stmt = $pdo->prepare('UPDATE messages SET read_at = NOW() WHERE id = ? AND recipient_id = ?');
        return $stmt->execute([$messageId, $userId]);
    }
}

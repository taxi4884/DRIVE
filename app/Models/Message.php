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

    public static function getConversationsByUser(int $userId): array
    {
        global $pdo;
        $stmt = $pdo->prepare('
            SELECT m.id, m.subject, m.body, m.created_at, l.other_id, b.Name AS other_name
            FROM messages m
            JOIN (
                SELECT 
                    CASE WHEN sender_id = :uid THEN recipient_id ELSE sender_id END AS other_id,
                    MAX(created_at) AS last_time
                FROM messages
                WHERE sender_id = :uid OR recipient_id = :uid
                GROUP BY other_id
            ) l ON (
                ((m.sender_id = :uid AND m.recipient_id = l.other_id) OR (m.sender_id = l.other_id AND m.recipient_id = :uid))
                AND m.created_at = l.last_time
            )
            JOIN Benutzer b ON b.BenutzerID = l.other_id
            ORDER BY m.created_at DESC
        ');
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getMessagesBetween(int $userId, int $otherId): array
    {
        global $pdo;
        $stmt = $pdo->prepare('
            SELECT m.id, m.subject, m.body, m.created_at, m.read_at,
                   m.sender_id, m.recipient_id,
                   sender.Name AS sender_name,
                   recipient.Name AS recipient_name
            FROM messages m
            JOIN Benutzer sender ON sender.BenutzerID = m.sender_id
            JOIN Benutzer recipient ON recipient.BenutzerID = m.recipient_id
            WHERE (m.sender_id = :uid AND m.recipient_id = :oid)
               OR (m.sender_id = :oid AND m.recipient_id = :uid)
            ORDER BY m.created_at ASC
        ');
        $stmt->execute(['uid' => $userId, 'oid' => $otherId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function findWithSender(int $messageId): ?array
    {
        global $pdo;
        $stmt = $pdo->prepare('
            SELECT m.id, m.subject, m.body, m.created_at, m.read_at,
                   m.sender_id, m.recipient_id,
                   sender.Name AS sender_name,
                   recipient.Name AS recipient_name
            FROM messages m
            JOIN Benutzer sender ON sender.BenutzerID = m.sender_id
            JOIN Benutzer recipient ON recipient.BenutzerID = m.recipient_id
            WHERE m.id = ?
        ');
        $stmt->execute([$messageId]);
        $message = $stmt->fetch(PDO::FETCH_ASSOC);

        return $message !== false ? $message : null;
    }

    public static function markConversationAsRead(int $userId, int $otherId): void
    {
        global $pdo;
        $stmt = $pdo->prepare('UPDATE messages SET read_at = NOW() WHERE recipient_id = ? AND sender_id = ? AND read_at IS NULL');
        $stmt->execute([$userId, $otherId]);
    }
}

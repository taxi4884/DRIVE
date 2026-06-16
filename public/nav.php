<?php
require_once __DIR__ . '/../includes/navigation.php';
require_once __DIR__ . '/../app/Models/Message.php';

use App\Models\Message;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

global $unreadMessageCount;
$unreadMessageCount = 0;
if (isset($_SESSION['user_id'])) {
    $unreadMessageCount = count(Message::getUnreadByUser((int) $_SESSION['user_id']));
}
global $sekundarRolle;
$currentPage = basename($_SERVER['PHP_SELF']);

$primaryRole    = $_SESSION['rolle'] ?? '';
$secondaryRoles = $_SESSION['sekundarRolle'] ?? [];

$favorites = [];
if (!empty($_SESSION['user_id'])) {
    $userId = (int) $_SESSION['user_id'];
    if (isset($pdo) && $pdo instanceof PDO) {
        try {
            $stmt = $pdo->prepare('SELECT menu_url FROM user_menu_favorites WHERE user_id = :uid');
            $stmt->execute(['uid' => $userId]);
            $favorites = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Throwable $e) {
            $favorites = [];
        }
    }
}

renderMenu(
    $primaryRole,
    $secondaryRoles,
    'top',
    $currentPage,
    $favorites
);
?>

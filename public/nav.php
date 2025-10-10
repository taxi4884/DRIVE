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

renderMenu(
    $primaryRole,
    $secondaryRoles,
    'top',
    $currentPage
);
?>

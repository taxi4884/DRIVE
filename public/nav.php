<?php
require_once __DIR__ . '/../includes/navigation.php';
$currentPage = basename($_SERVER['PHP_SELF']);

$primaryRole    = $_SESSION['rolle'] ?? '';
$secondaryRoles = $sekundarRolle ?? [];

renderMenu(
    $primaryRole,
    $secondaryRoles,
    'top',
    $currentPage
);
?>

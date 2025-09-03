<?php
require_once __DIR__ . '/../includes/navigation.php';
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

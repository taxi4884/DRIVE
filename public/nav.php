<?php
require_once '../includes/navigation.php';
$currentPage = basename($_SERVER['PHP_SELF']);
renderMenu(
    $_SESSION['rolle'] ?? '',
    $sekundarRolle ?? '',
    'top',
    $currentPage
);
?>

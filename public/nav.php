<?php
require_once '../includes/navigation.php';
renderMenu(
    $_SESSION['rolle'] ?? '',
    $sekundarRolle ?? '',
    'top'
);
?>
